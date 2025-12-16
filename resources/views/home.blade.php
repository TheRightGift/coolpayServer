@extends('layouts.app')

@section('content')
<home-component :user="{{ auth()->check() ? auth()->user()->toJson() : 'null' }}"></home-component>
@endsection
