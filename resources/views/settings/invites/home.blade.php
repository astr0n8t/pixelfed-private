@extends('layouts.app')

@section('content')
<div class="container">
<invites-home></invites-home>
</div>
@endsection

@push('scripts')
<script type="text/javascript" src="{{ mix('js/invites.js') }}"></script>
<script type="text/javascript">App.boot();</script>
@endpush
