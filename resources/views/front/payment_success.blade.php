@extends('front.layout.app')

@section('main_content')
<div class="page-top" style="background-image: url('{{ asset('uploads/banner.jpg') }}'); background-size: cover; background-position: center;">
    <div class="bg" style="background: rgba(0,0,0,0.6);"></div>
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <h2 class="text-white">Payment Successful</h2>
            </div>
        </div>
    </div>
</div>

<div class="page-content bg-light py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-lg border-0 rounded-lg overflow-hidden">
                    <div class="card-body p-5 text-center">
                        <div class="success-icon mb-4">
                            <i class="fa fa-check-circle text-success" style="font-size: 80px;"></i>
                        </div>
                        <h2 class="font-weight-bold mb-2">Thank You!</h2>
                        <p class="lead text-muted mb-5">Your payment has been processed successfully. Your booking is now confirmed.</p>

                        <div class="row text-left mb-5">
                            <div class="col-md-6 mb-4">
                                <div class="p-3 border rounded bg-white h-100 shadow-sm">
                                    <h5 class="border-bottom pb-2 mb-3 text-primary"><i class="fa fa-info-circle mr-2"></i> Booking Info</h5>
                                    <div class="mb-2"><strong>Order Number:</strong> #{{ $order->order_no }}</div>
                                    <div class="mb-2"><strong>Transaction ID:</strong> {{ $order->transaction_id }}</div>
                                    <div class="mb-2"><strong>Payment Method:</strong> {{ $order->payment_method }}</div>
                                    <div class="mb-2"><strong>Total Paid:</strong> <span class="text-success font-weight-bold">₦{{ number_format($order->paid_amount, 2) }}</span></div>
                                    <div class="mb-2"><strong>Booking Date:</strong> {{ $order->booking_date }}</div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <div class="p-3 border rounded bg-white h-100 shadow-sm">
                                    <h5 class="border-bottom pb-2 mb-3 text-primary"><i class="fa fa-building mr-2"></i> Hotel Info</h5>
                                    <div class="mb-2"><strong>Hotel:</strong> BELMORRIS HOTEL & BAR</div>
                                    <div class="mb-2"><strong>Email:</strong> {{ $global_setting_data->footer_email }}</div>
                                    <div class="mb-2"><strong>Phone:</strong> {{ $global_setting_data->footer_phone }}</div>
                                    <div class="mb-2"><strong>Address:</strong> {{ $global_setting_data->footer_address }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="room-details text-left mb-5">
                            <h4 class="mb-4 text-dark border-bottom pb-2">Room Details</h4>
                            @forelse($order_detail as $item)
                                @php
                                    $room = DB::table('rooms')->where('id', $item->room_id)->first();
                                @endphp
                                @if($room)
                                <div class="card mb-3 border-0 shadow-sm">
                                    <div class="card-body py-3">
                                        <div class="row align-items-center">
                                            <div class="col-md-6">
                                                <h6 class="font-weight-bold mb-1 text-primary">{{ $room->name }}</h6>
                                                <small class="text-muted"><i class="fa fa-calendar mr-1"></i> {{ $item->checkin_date }} to {{ $item->checkout_date }}</small>
                                            </div>
                                            <div class="col-md-3 text-center">
                                                <small class="d-block text-muted">Guests</small>
                                                <span class="font-weight-bold">{{ $item->adult }} Adults, {{ $item->children }} Children</span>
                                            </div>
                                            <div class="col-md-3 text-right">
                                                <small class="d-block text-muted">Subtotal</small>
                                                <span class="h6 font-weight-bold">₦{{ number_format($item->subtotal, 2) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endif
                            @empty
                                <div class="alert alert-warning text-center">No room details found for this order.</div>
                            @endforelse
                        </div>

                        <div class="action-buttons d-flex justify-content-center">
                            <a href="{{ route('home') }}" class="btn btn-primary btn-lg px-4 rounded-pill mr-3 shadow-sm">
                                <i class="fa fa-home mr-2"></i> Back to Home
                            </a>
                            <button onclick="window.print()" class="btn btn-outline-secondary btn-lg px-4 rounded-pill shadow-sm">
                                <i class="fa fa-print mr-2"></i> Print Summary
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-4">
                    <p class="text-muted">A confirmation email has been sent to <strong>{{ $order->billing_email }}</strong>.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .success-icon i {
        animation: scaleUp 0.5s ease-out;
    }
    @keyframes scaleUp {
        0% { transform: scale(0); opacity: 0; }
        100% { transform: scale(1); opacity: 1; }
    }
    .card { border-radius: 1rem; }
    .btn-primary { background-color: {{ $global_setting_data->theme_color_1 }}; border-color: {{ $global_setting_data->theme_color_1 }}; }
    .btn-primary:hover { background-color: {{ $global_setting_data->theme_color_2 }}; border-color: {{ $global_setting_data->theme_color_2 }}; }
    .text-primary { color: {{ $global_setting_data->theme_color_1 }} !important; }
    @media print {
        .page-top, .footer, .scroll-top, .action-buttons, .main-nav { display: none !important; }
        .page-content { padding: 0 !important; }
        .card { box-shadow: none !important; border: 1px solid #ddd !important; }
    }
</style>
@endsection
