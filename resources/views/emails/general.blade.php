@component('mail::message')


{!! $titulo !!}

<br/>

{!! $html !!}

<br/><br/>

Gracias por su atención,<br>
{{ config('app.name') }}
@endcomponent
