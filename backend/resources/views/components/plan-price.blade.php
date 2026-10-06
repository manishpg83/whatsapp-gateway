{{-- A plan's monthly price in the visitor's currency (₹ by default, R on .za
     domains — see App\Support\Currency). $plan is a Plan or a plan row as an array. --}}
@props(['plan', 'per' => '/mo'])
@php($local = \App\Models\Plan::localPrice($plan))
@if ($local === null)
    <span class="fs-5">Contact us</span>
@elseif ($local > 0)
    {{ \App\Support\Currency::symbol() }}{{ number_format($local) }}<span>{{ $per }}</span>
@else
    Free
@endif
