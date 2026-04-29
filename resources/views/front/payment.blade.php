@extends('front.layout.app')

@section('main_content')
    <div class="page-top"
        style="background-image: url('{{ asset('uploads/banner.jpg') }}'); background-size: cover; background-position: center;">
        <div class="bg" style="background: rgba(0,0,0,0.6);"></div>
        <div class="container">
            <div class="row">
                <div class="col-md-12">
                    <h2 class="text-white">{{ $global_page_data->payment_heading }}</h2>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-center" style="background: transparent;">
                            <li class="breadcrumb-item"><a href="{{ route('home') }}" class="text-white">Home</a></li>
                            <li class="breadcrumb-item active text-white-50" aria-current="page">Payment</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <div class="page-content bg-light py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-8">
                    <!-- Payment Method Card -->
                    <div class="card shadow-sm border-0 mb-4 rounded-lg overflow-hidden">
                        <div class="card-header bg-white border-0 py-3">
                            <h4 class="mb-0 text-primary"><i class="fa fa-credit-card mr-2"></i> Choose Payment Method</h4>
                        </div>
                        <div class="card-body p-4">
                            <div
                                class="payment-selection p-4 border rounded-lg text-center bg-white hover-shadow transition">
                                <img src="https://upload.wikimedia.org/wikipedia/commons/0/0b/Paystack_Logo.png"
                                    alt="Paystack" class="img-fluid mb-3" style="max-width: 200px;">
                                <p class="text-muted mb-4">Pay securely using your Debit/Credit card or Bank Transfer via
                                    Paystack.</p>

                                @php
                                    $total_price = 0;
                                    foreach (session()->get('cart_room_id') as $key => $room_id) {
                                        $room_data = DB::table('rooms')->where('id', $room_id)->first();
                                        $d1 = explode('/', session()->get('cart_checkin_date')[$key]);
                                        $d2 = explode('/', session()->get('cart_checkout_date')[$key]);
                                        $d1_new = $d1[2] . '-' . $d1[1] . '-' . $d1[0];
                                        $d2_new = $d2[2] . '-' . $d2[1] . '-' . $d2[0];
                                        $t1 = strtotime($d1_new);
                                        $t2 = strtotime($d2_new);
                                        $diff = ($t2 - $t1) / 60 / 60 / 24;
                                        $total_price += ($room_data->price * $diff);
                                    }

                                    $fee = 0;
                                    if ($global_setting_data->paystack_fee_charge_by == 'Customer') {
                                        if ($total_price < 2500) {
                                            $total_with_fee = $total_price / (1 - 0.015);
                                        } else {
                                            $total_with_fee = ($total_price + 100) / (1 - 0.015);
                                        }
                                        $fee = ceil($total_with_fee - $total_price);
                                        if ($fee > 2000)
                                            $fee = 2000;
                                    }
                                    $final_total_price = $total_price + $fee;
                                    $cents = $final_total_price * 100;
                                    $customer_email = session()->get('billing_email');
                                @endphp

                                <button type="button" class="btn btn-primary btn-lg px-5 rounded-pill shadow"
                                    onclick="payWithPaystack()">
                                    <i class="fa fa-lock mr-2"></i> Pay ₦{{ number_format($final_total_price, 2) }} Now
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Billing Information Card -->
                    <div class="card shadow-sm border-0 mb-4 rounded-lg overflow-hidden">
                        <div class="card-header bg-white border-0 py-3">
                            <h4 class="mb-0 text-primary"><i class="fa fa-address-book mr-2"></i> Billing Information</h4>
                        </div>
                        <div class="card-body p-4 bg-white">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <small class="text-muted d-block text-uppercase font-weight-bold">Full Name</small>
                                    <span class="h6">{{ session()->get('billing_name') }}</span>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <small class="text-muted d-block text-uppercase font-weight-bold">Email Address</small>
                                    <span class="h6">{{ session()->get('billing_email') }}</span>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <small class="text-muted d-block text-uppercase font-weight-bold">Phone Number</small>
                                    <span class="h6">{{ session()->get('billing_phone') }}</span>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <small class="text-muted d-block text-uppercase font-weight-bold">Country</small>
                                    <span class="h6">{{ session()->get('billing_country') }}</span>
                                </div>
                                <div class="col-12">
                                    <small class="text-muted d-block text-uppercase font-weight-bold">Address</small>
                                    <span class="h6">{{ session()->get('billing_address') }},
                                        {{ session()->get('billing_city') }}, {{ session()->get('billing_state') }}
                                        {{ session()->get('billing_zip') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <!-- Order Summary Card -->
                    <div class="card shadow-sm border-0 rounded-lg overflow-hidden sticky-top" style="top: 100px;">
                        <div class="card-header bg-primary text-white border-0 py-3">
                            <h4 class="mb-0 h5"><i class="fa fa-shopping-cart mr-2"></i> Order Summary</h4>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                @foreach(session()->get('cart_room_id') as $key => $room_id)
                                    @php
                                        $room_data = DB::table('rooms')->where('id', $room_id)->first();
                                        $checkin = session()->get('cart_checkin_date')[$key];
                                        $checkout = session()->get('cart_checkout_date')[$key];
                                    @endphp
                                    <li class="list-group-item p-3 border-0 border-bottom">
                                        <div class="d-flex justify-content-between align-items-start mb-1">
                                            <h6 class="mb-0 font-weight-bold text-dark">{{ $room_data->name }}</h6>
                                            <span
                                                class="text-primary font-weight-bold">₦{{ number_format($room_data->price, 0) }}</span>
                                        </div>
                                        <small class="text-muted d-block"><i class="fa fa-calendar-check-o mr-1"></i>
                                            {{ $checkin }} - {{ $checkout }}</small>
                                        <small class="text-muted d-block"><i class="fa fa-users mr-1"></i>
                                            {{ session()->get('cart_adult')[$key] }} Adults,
                                            {{ session()->get('cart_children')[$key] }} Children</small>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="card-footer bg-white border-0 p-4">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Subtotal</span>
                                <span class="font-weight-bold text-dark">₦{{ number_format($total_price, 2) }}</span>
                            </div>
                            @if($fee > 0)
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Processing Fee</span>
                                    <span class="font-weight-bold text-dark">₦{{ number_format($fee, 2) }}</span>
                                </div>
                            @endif
                            <hr class="my-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="h5 mb-0 font-weight-bold">Total Amount</span>
                                <span
                                    class="h4 mb-0 font-weight-bold text-primary">₦{{ number_format($final_total_price, 2) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://js.paystack.co/v1/inline.js"></script>
    <script>
        function payWithPaystack() {
            var handler = PaystackPop.setup({
                key: '{{ $global_setting_data->paystack_public_key }}',
                email: '{{ $customer_email }}',
                amount: {{ $cents }},
                currency: 'NGN',
                ref: '' + Math.floor((Math.random() * 1000000000) + 1),
                callback: function (response) {
                    window.location.href = "{{ url('payment/paystack/' . $final_total_price) }}?reference=" + response.reference;
                },
                onClose: function () {
                    //alert('Window closed.');
                }
            });
            handler.openIframe();
        }
    </script>

    <style>
        .transition {
            transition: all 0.3s ease;
        }

        .hover-shadow:hover {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
            transform: translateY(-3px);
        }

        .rounded-lg {
            border-radius: 0.75rem !important;
        }

        .page-top {
            position: relative;
            height: 250px;
            display: flex;
            align-items: center;
            text-align: center;
        }

        .bg-light {
            background-color: #f8f9fa !important;
        }

        .btn-primary {
            background-color:
                {{ $global_setting_data->theme_color_1 }}
            ;
            border-color:
                {{ $global_setting_data->theme_color_1 }}
            ;
        }

        .btn-primary:hover {
            background-color:
                {{ $global_setting_data->theme_color_2 }}
            ;
            border-color:
                {{ $global_setting_data->theme_color_2 }}
            ;
            opacity: 0.9;
        }

        .text-primary {
            color:
                {{ $global_setting_data->theme_color_1 }}
                !important;
        }

        .bg-primary {
            background-color:
                {{ $global_setting_data->theme_color_1 }}
                !important;
        }
    </style>
@endsection