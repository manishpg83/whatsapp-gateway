{{-- The InstaMessage logo image (icon + name), from
     public/images/brand/. Styles are in app.css (.brand-logo).
       theme: 'dark'  = dark "Insta" text, for light backgrounds (top bars)
              'light' = white "Insta" text, for dark backgrounds (sidebar, footer)
       size:  'sm' | 'md' | 'lg' --}}
@props(['theme' => 'dark', 'size' => 'md'])

<img src="{{ asset($theme === 'light' ? 'images/brand/instamessage-logo-light.png' : 'images/brand/instamessage-logo.png') }}"
     alt="{{ config('app.name') }}" width="1000" height="182"
     {{ $attributes->class(['brand-logo', 'brand-logo--'.$size]) }}>
