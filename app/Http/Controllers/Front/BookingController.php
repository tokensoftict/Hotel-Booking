<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\BookedRoom;
use App\Models\Room;
use Auth;
use DB;
use App\Mail\Websitemail;
use Illuminate\Support\Facades\Hash;
// removed paypal/stripe imports

class BookingController extends Controller
{
    
    public function cart_submit(Request $request)
    {
        $request->validate([
            'room_id' => 'required',
            'checkin_checkout' => 'required',
            'adult' => 'required'
        ]);

        $dates = explode(' - ',$request->checkin_checkout);
        $checkin_date = $dates[0];
        $checkout_date = $dates[1];

        $d1 = explode('/',$checkin_date);
        $d2 = explode('/',$checkout_date);
        $d1_new = $d1[2].'-'.$d1[1].'-'.$d1[0];
        $d2_new = $d2[2].'-'.$d2[1].'-'.$d2[0];
        $t1 = strtotime($d1_new);
        $t2 = strtotime($d2_new);

        $cnt = 1;
        while(1) {
            if($t1>=$t2) {
                break;
            }
            $single_date = date('d/m/Y',$t1);
            $total_already_booked_rooms = BookedRoom::where('booking_date',$single_date)->where('room_id',$request->room_id)->count();

            $arr = Room::where('id',$request->room_id)->first();
            $total_allowed_rooms = $arr->total_rooms;

            if($total_already_booked_rooms == $total_allowed_rooms) {
                $cnt = 0;
                break;
            }
            $t1 = strtotime('+1 day',$t1);
        }

        if($cnt == 0) {
            return redirect()->back()->with('error', 'Maximum number of this room is already booked');
        }        
        
        session()->push('cart_room_id',$request->room_id);
        session()->push('cart_checkin_date',$checkin_date);
        session()->push('cart_checkout_date',$checkout_date);
        session()->push('cart_adult',$request->adult);
        session()->push('cart_children',$request->children);

        return redirect()->back()->with('success', 'Room is added to the cart successfully.');
    }

    public function cart_view()
    {
        return view('front.cart');
    }

    public function cart_delete($id)
    {
        $arr_cart_room_id = array();
        $i=0;
        foreach(session()->get('cart_room_id') as $value) {
            $arr_cart_room_id[$i] = $value;
            $i++;
        }

        $arr_cart_checkin_date = array();
        $i=0;
        foreach(session()->get('cart_checkin_date') as $value) {
            $arr_cart_checkin_date[$i] = $value;
            $i++;
        }

        $arr_cart_checkout_date = array();
        $i=0;
        foreach(session()->get('cart_checkout_date') as $value) {
            $arr_cart_checkout_date[$i] = $value;
            $i++;
        }

        $arr_cart_adult = array();
        $i=0;
        foreach(session()->get('cart_adult') as $value) {
            $arr_cart_adult[$i] = $value;
            $i++;
        }

        $arr_cart_children = array();
        $i=0;
        foreach(session()->get('cart_children') as $value) {
            $arr_cart_children[$i] = $value;
            $i++;
        }

        session()->forget('cart_room_id');
        session()->forget('cart_checkin_date');
        session()->forget('cart_checkout_date');
        session()->forget('cart_adult');
        session()->forget('cart_children');

        for($i=0;$i<count($arr_cart_room_id);$i++)
        {
            if($arr_cart_room_id[$i] == $id) 
            {
                continue;    
            }
            else
            {
                session()->push('cart_room_id',$arr_cart_room_id[$i]);
                session()->push('cart_checkin_date',$arr_cart_checkin_date[$i]);
                session()->push('cart_checkout_date',$arr_cart_checkout_date[$i]);
                session()->push('cart_adult',$arr_cart_adult[$i]);
                session()->push('cart_children',$arr_cart_children[$i]);
            }
        }

        return redirect()->back()->with('success', 'Cart item is deleted.');

    }


    public function checkout()
    {
        if(!session()->has('cart_room_id')) {
            return redirect()->back()->with('error', 'There is no item in the cart');
        }

        return view('front.checkout');
    }

    public function payment(Request $request)
    {
        if(!session()->has('cart_room_id')) {
            return redirect()->back()->with('error', 'There is no item in the cart');
        }

        $rules = [
            'billing_name' => 'required',
            'billing_email' => 'required|email',
            'billing_phone' => 'required',
            'billing_country' => 'required',
            'billing_address' => 'required',
            'billing_state' => 'required',
            'billing_city' => 'required',
            'billing_zip' => 'required'
        ];

        if ($request->has('register_account')) {
            $rules['password'] = 'required|min:6';
            $rules['billing_email'] = 'required|email|unique:customers,email';
        }

        $request->validate($rules);

        if ($request->has('register_account')) {
            $customer = new Customer();
            $customer->name = $request->billing_name;
            $customer->email = $request->billing_email;
            $customer->password = Hash::make($request->password);
            $customer->phone = $request->billing_phone;
            $customer->country = $request->billing_country;
            $customer->address = $request->billing_address;
            $customer->state = $request->billing_state;
            $customer->city = $request->billing_city;
            $customer->zip = $request->billing_zip;
            $customer->status = 1; // Active status
            $customer->save();

            Auth::guard('customer')->login($customer);
        }

        session()->put('billing_name',$request->billing_name);
        session()->put('billing_email',$request->billing_email);
        session()->put('billing_phone',$request->billing_phone);
        session()->put('billing_country',$request->billing_country);
        session()->put('billing_address',$request->billing_address);
        session()->put('billing_state',$request->billing_state);
        session()->put('billing_city',$request->billing_city);
        session()->put('billing_zip',$request->billing_zip);

        return view('front.payment');
    }

    public function paystack(Request $request, $final_price)
    {
        $reference = $request->reference;

        if(!$reference){
            return redirect()->route('home')->with('error', 'No reference supplied');
        }

        $secret_key = \App\Models\Setting::first()->paystack_secret_key;
        $curl = curl_init();
        
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . rawurlencode($reference),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "accept: application/json",
                "authorization: Bearer " . $secret_key,
                "cache-control: no-cache"
            ],
        ));
        
        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);
        
        if ($err) {
            return redirect()->route('home')->with('error', 'Payment failed: ' . $err);
        }

        $tranx = json_decode($response);

        if(!$tranx->status || $tranx->data->status !== 'success') {
            return redirect()->route('home')->with('error', 'Payment failed or was not successful.');
        }

        $order_no = time();
        $transaction_id = $tranx->data->reference;

        $paid_amount = $tranx->data->amount / 100;

        $obj = new Order();
        $obj->customer_id = Auth::guard('customer')->check() ? Auth::guard('customer')->user()->id : 0;
        $obj->billing_name = session()->get('billing_name');
        $obj->billing_email = session()->get('billing_email');
        $obj->billing_phone = session()->get('billing_phone');
        $obj->billing_country = session()->get('billing_country');
        $obj->billing_address = session()->get('billing_address');
        $obj->billing_state = session()->get('billing_state');
        $obj->billing_city = session()->get('billing_city');
        $obj->billing_zip = session()->get('billing_zip');
        $obj->order_no = $order_no;
        $obj->transaction_id = $transaction_id;
        $obj->payment_method = 'Paystack';
        $obj->card_last_digit = $tranx->data->authorization->last4 ?? '';
        $obj->paid_amount = $paid_amount;
        $obj->booking_date = date('d/m/Y');
        $obj->status = 'Completed';
        $obj->save();

        $order_id = $obj->id;
        
        $arr_cart_room_id = array();
        $i=0;
        foreach(session()->get('cart_room_id') as $value) {
            $arr_cart_room_id[$i] = $value;
            $i++;
        }

        $arr_cart_checkin_date = array();
        $i=0;
        foreach(session()->get('cart_checkin_date') as $value) {
            $arr_cart_checkin_date[$i] = $value;
            $i++;
        }

        $arr_cart_checkout_date = array();
        $i=0;
        foreach(session()->get('cart_checkout_date') as $value) {
            $arr_cart_checkout_date[$i] = $value;
            $i++;
        }

        $arr_cart_adult = array();
        $i=0;
        foreach(session()->get('cart_adult') as $value) {
            $arr_cart_adult[$i] = $value;
            $i++;
        }

        $arr_cart_children = array();
        $i=0;
        foreach(session()->get('cart_children') as $value) {
            $arr_cart_children[$i] = $value;
            $i++;
        }

        for($i=0;$i<count($arr_cart_room_id);$i++)
        {
            $r_info = Room::where('id',$arr_cart_room_id[$i])->first();
            $d1 = explode('/',$arr_cart_checkin_date[$i]);
            $d2 = explode('/',$arr_cart_checkout_date[$i]);
            $d1_new = $d1[2].'-'.$d1[1].'-'.$d1[0];
            $d2_new = $d2[2].'-'.$d2[1].'-'.$d2[0];
            $t1 = strtotime($d1_new);
            $t2 = strtotime($d2_new);
            $diff = ($t2-$t1)/60/60/24;
            $sub = $r_info->price*$diff;

            $obj = new OrderDetail();
            $obj->order_id = $order_id;
            $obj->room_id = $arr_cart_room_id[$i];
            $obj->order_no = $order_no;
            $obj->checkin_date = $arr_cart_checkin_date[$i];
            $obj->checkout_date = $arr_cart_checkout_date[$i];
            $obj->adult = $arr_cart_adult[$i];
            $obj->children = $arr_cart_children[$i];
            $obj->subtotal = $sub;
            $obj->save();

            while(1) {
                if($t1>=$t2) {
                    break;
                }

                $obj = new BookedRoom();
                $obj->booking_date = date('d/m/Y',$t1);
                $obj->order_no = $order_no;
                $obj->room_id = $arr_cart_room_id[$i];
                $obj->save();

                $t1 = strtotime('+1 day',$t1);
            }

        }

        $setting = \App\Models\Setting::where('id', 1)->first();
        $subject = 'Booking Confirmation - Order #' . $order_no;
        
        $message = '<div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; border: 1px solid #ddd; border-radius: 10px; overflow: hidden;">';
        $message .= '<div style="background-color: '.$setting->theme_color_1.'; color: #fff; padding: 20px; text-align: center;">';
        $message .= '<h2 style="margin: 0;">Booking Confirmation</h2>';
        $message .= '</div>';
        $message .= '<div style="padding: 20px;">';
        $message .= '<p>Dear Customer,</p>';
        $message .= '<p>Thank you for choosing <strong>BELMORRIS HOTEL & BAR</strong>. Your booking has been successfully processed.</p>';
        
        $message .= '<div style="background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 20px;">';
        $message .= '<h3 style="margin-top: 0; color: '.$setting->theme_color_1.'; border-bottom: 1px solid #ddd; padding-bottom: 5px;">Order Information</h3>';
        $message .= '<table style="width: 100%; border-collapse: collapse;">';
        $message .= '<tr><td style="padding: 5px 0;"><strong>Order No:</strong></td><td style="text-align: right;">#'.$order_no.'</td></tr>';
        $message .= '<tr><td style="padding: 5px 0;"><strong>Transaction Id:</strong></td><td style="text-align: right;">'.$transaction_id.'</td></tr>';
        $message .= '<tr><td style="padding: 5px 0;"><strong>Payment Method:</strong></td><td style="text-align: right;">Paystack</td></tr>';
        $message .= '<tr><td style="padding: 5px 0;"><strong>Paid Amount:</strong></td><td style="text-align: right; color: #28a745; font-weight: bold;">₦'.number_format($paid_amount, 2).'</td></tr>';
        $message .= '<tr><td style="padding: 5px 0;"><strong>Booking Date:</strong></td><td style="text-align: right;">'.date('d/m/Y').'</td></tr>';
        $message .= '</table>';
        $message .= '</div>';

        $message .= '<h3 style="color: '.$setting->theme_color_1.';">Room Details</h3>';
        foreach(session()->get('cart_room_id') as $key => $room_id) {
            $r_info = Room::where('id', $room_id)->first();
            $checkin = session()->get('cart_checkin_date')[$key];
            $checkout = session()->get('cart_checkout_date')[$key];
            $adult = session()->get('cart_adult')[$key];
            $children = session()->get('cart_children')[$key];

            $message .= '<div style="border: 1px solid #eee; padding: 10px; border-radius: 5px; margin-bottom: 10px;">';
            $message .= '<div style="font-weight: bold; font-size: 16px; color: #333;">'.$r_info->name.'</div>';
            $message .= '<div style="font-size: 14px; color: #666;">'.$checkin.' to '.$checkout.'</div>';
            $message .= '<div style="font-size: 14px; color: #666;">Adults: '.$adult.', Children: '.$children.'</div>';
            $message .= '</div>';
        }

        $message .= '<div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #888; text-align: center;">';
        $message .= '<p>'.$setting->footer_address.'<br>Email: '.$setting->footer_email.' | Phone: '.$setting->footer_phone.'</p>';
        $message .= '</div>';
        $message .= '</div>';
        $message .= '</div>';

        $customer_email = session()->get('billing_email');
        \Mail::to($customer_email)->send(new Websitemail($subject, $message));

        // Create Admin Alert Message
        $admin_message = '<div style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; border: 2px solid #ffc107; border-radius: 10px; overflow: hidden;">';
        $admin_message .= '<div style="background-color: #ffc107; color: #000; padding: 20px; text-align: center;">';
        $admin_message .= '<h2 style="margin: 0;">⚠️ NEW ORDER ALERT</h2>';
        $admin_message .= '</div>';
        $admin_message .= '<div style="padding: 20px;">';
        $admin_message .= '<p style="font-size: 16px;">Hello Admin,</p>';
        $admin_message .= '<p>A new booking has just been placed. Please find the details below to prepare for the arrival:</p>';
        
        // Customer Information
        $admin_message .= '<div style="background-color: #f0f4f8; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 5px solid #2196f3;">';
        $admin_message .= '<h3 style="margin-top: 0; color: #2196f3; border-bottom: 1px solid #ddd; padding-bottom: 5px;">Customer Information</h3>';
        $admin_message .= '<table style="width: 100%; border-collapse: collapse;">';
        $admin_message .= '<tr><td style="padding: 5px 0;"><strong>Name:</strong></td><td style="text-align: right;">'.session()->get('billing_name').'</td></tr>';
        $admin_message .= '<tr><td style="padding: 5px 0;"><strong>Email:</strong></td><td style="text-align: right;">'.session()->get('billing_email').'</td></tr>';
        $admin_message .= '<tr><td style="padding: 5px 0;"><strong>Phone:</strong></td><td style="text-align: right;">'.session()->get('billing_phone').'</td></tr>';
        $admin_message .= '<tr><td style="padding: 5px 0;"><strong>Location:</strong></td><td style="text-align: right;">'.session()->get('billing_city').', '.session()->get('billing_country').'</td></tr>';
        $admin_message .= '</table>';
        $admin_message .= '</div>';

        // Order Information
        $admin_message .= '<div style="background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 5px solid '.$setting->theme_color_1.';">';
        $admin_message .= '<h3 style="margin-top: 0; color: '.$setting->theme_color_1.'; border-bottom: 1px solid #ddd; padding-bottom: 5px;">Order Summary</h3>';
        $admin_message .= '<table style="width: 100%; border-collapse: collapse;">';
        $admin_message .= '<tr><td style="padding: 5px 0;"><strong>Order No:</strong></td><td style="text-align: right;">#'.$order_no.'</td></tr>';
        $admin_message .= '<tr><td style="padding: 5px 0;"><strong>Transaction ID:</strong></td><td style="text-align: right;">'.$transaction_id.'</td></tr>';
        $admin_message .= '<tr><td style="padding: 5px 0;"><strong>Amount Paid:</strong></td><td style="text-align: right; color: #28a745; font-weight: bold;">₦'.number_format($paid_amount, 2).'</td></tr>';
        $admin_message .= '</table>';
        $admin_message .= '</div>';

        // Room Details
        $admin_message .= '<h3 style="color: #333;">Rooms Booked</h3>';
        foreach(session()->get('cart_room_id') as $key => $room_id) {
            $r_info = Room::where('id', $room_id)->first();
            $admin_message .= '<div style="border: 1px solid #eee; padding: 10px; border-radius: 5px; margin-bottom: 10px; background: #fff;">';
            $admin_message .= '<div style="font-weight: bold; color: #333;">'.$r_info->name.'</div>';
            $admin_message .= '<div style="font-size: 14px; color: #666;">Dates: '.session()->get('cart_checkin_date')[$key].' to '.session()->get('cart_checkout_date')[$key].'</div>';
            $admin_message .= '<div style="font-size: 14px; color: #666;">Guests: '.session()->get('cart_adult')[$key].' Adults, '.session()->get('cart_children')[$key].' Children</div>';
            $admin_message .= '</div>';
        }

        $admin_message .= '<p style="text-align: center; margin-top: 20px;"><a href="'.url('/admin').'" style="background-color: #2196f3; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;">View in Admin Panel</a></p>';
        $admin_message .= '</div>';
        $admin_message .= '</div>';

        // Send to Hotel Email
        if($setting->top_bar_email) {
            \Mail::to($setting->top_bar_email)->send(new Websitemail('NEW ORDER ALERT - Order #'.$order_no, $admin_message));
        }

        session()->forget('cart_room_id');
        session()->forget('cart_checkin_date');
        session()->forget('cart_checkout_date');
        session()->forget('cart_adult');
        session()->forget('cart_children');
        session()->forget('billing_name');
        session()->forget('billing_email');
        session()->forget('billing_phone');
        session()->forget('billing_country');
        session()->forget('billing_address');
        session()->forget('billing_state');
        session()->forget('billing_city');
        session()->forget('billing_zip');

        return redirect()->route('payment_success', $order_no)->with('success', 'Payment is successful');
    }

    public function payment_success($order_no)
    {
        $order = Order::where('order_no', $order_no)->first();
        if(!$order) {
            return redirect()->route('home');
        }
        $order_detail = OrderDetail::where('order_no', $order->order_no)->get();
        return view('front.payment_success', compact('order', 'order_detail'));
    }
}
