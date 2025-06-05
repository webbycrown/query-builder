@extends('wc_querybuilder::layout')

@section('css')
{{-- Include custom query builder CSS --}}
@include('wc_querybuilder::css.style')
@endsection
@section('content')
<div class="container">
	<div class="row">
		 <div class="col-12">
		 	 <div class="card">

		 	 	{{-- Card Header --}}
		 	 	<div class="card-header">

		 	 		<div class="d-flex justify-content-between">
		 	 			<h2>{{ __('querybuilder::messages.scheduled_report') }}</h2>
		 	 			<div>
		 	 				<a href="{{ route( 'queries.reports.index' ) }}" class="btn btn-secondary">{{ __('querybuilder::messages.back_button') }}</a>
		 	 				<button type="button" class="btn btn-primary btn-saveSchedule">{{optional($scheduled_reports)->id > 0 ? __('querybuilder::messages.update_schedule') : __('querybuilder::messages.save_schedule') }} </button>
		 	 			</div>
		 	 		</div>

		 	 	</div>
                {{-- End Card Header --}}

		 	 	{{-- Card body --}}
                <div class="card-body">
                    {{-- Schedule Report Form --}}
                    <form id="scheduledReportForm">
                        <input type="hidden" name="id" value="{{ $scheduled_reports->id ?? 0 }}">
                        <div class="row">
                        {{-- Report Type --}}
                        <div class="col-md-4 mb-3">
                            <label for="report_type" class="form-label">{{ __('querybuilder::messages.report_type') }}</label>
                            <select name="report_type" id="report_type" class="form-select">
                                @if( isset($query_forms) && count($query_forms) > 0 )
                                    @foreach( $query_forms as $query_form )
                                    <option class="{{ $query_form->id }}">{{ $query_form->title }}</option>
                                    @endforeach
                                @endif
                            </select>
                            <span class="text-danger error" id="report_type-error" ></span>
                        </div>

                        {{-- Frequency --}}
                        <div class="col-md-4 mb-3">
                            <label for="frequency" class="form-label">{{ __('querybuilder::messages.frequency') }}</label>
                            <select name="frequency" id="frequency" class="form-select">
                                <option value="daily" {{ optional($scheduled_reports)->frequency == 'daily' ? 'selected' : '' }}>{{ __('querybuilder::messages.daily') }}</option>
                                <option value="weekly" {{ optional($scheduled_reports)->frequency == 'weekly' ? 'selected' : '' }}>{{ __('querybuilder::messages.weekly') }}</option>
                                <option value="monthly" {{ optional($scheduled_reports)->frequency == 'monthly' ? 'selected' : '' }}>{{ __('querybuilder::messages.monthly') }}</option>
                            </select>
                            <span class="text-danger error" id="frequency-error" ></span>
                        </div>

                        {{-- Delivery Time --}}
                        <div class="col-md-4 mb-3">
                            <label for="time" class="form-label">{{ __('querybuilder::messages.delivery_time') }}</label>
                            <input type="time" name="time" id="time" class="form-control" value="{{ optional($scheduled_reports)->time ? \Carbon\Carbon::parse($scheduled_reports->time)->format('H:i') : '' }}">
                            <span class="text-danger error" id="time-error" ></span>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="email" class="form-label">{{ __('querybuilder::messages.to_email') }}</label>
                            <input type="text" name="email" class="form-control"  value="{{ $scheduled_reports->email ?? '' }}" placeholder="recipient@example.com">
                            <span class="text-danger error" id="email-error" ></span>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="cc_email" class="form-label">{{ __('querybuilder::messages.cc_email_optional') }}</label>
                            <input type="text" name="cc_email" class="form-control" value="{{ $scheduled_reports->cc_email ?? '' }}" placeholder="cc@example.com,cc@example.com">
                            <span class="text-danger error" id="cc_email-error" ></span>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="bcc_email" class="form-label">{{ __('querybuilder::messages.bcc_email_optional') }}</label>
                            <input type="text" name="bcc_email" class="form-control" value="{{ $scheduled_reports->bcc_email ?? '' }}" placeholder="bcc@example.com,bcc@example.com">
                            <span class="text-danger error" id="bcc_email-error" ></span>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="subject" class="form-label">{{ __('querybuilder::messages.email_subject') }}</label>
                            <input type="text" name="subject" class="form-control" value="{{ $scheduled_reports->subject ?? '' }}" placeholder="{{ __('querybuilder::messages.default_subject_placeholder') }}">
                            <span class="text-danger error" id="subject-error" ></span>
                        </div>


                        {{-- File Format --}}
                        <div class="col-md-4 mb-3">
                            <label for="format" class="form-label">{{ __('querybuilder::messages.file_format') }}</label>
                            <select name="format" id="format" class="form-select">
                                <option value="pdf" {{ optional($scheduled_reports)->format == 'pdf' ? 'selected' : '' }}>{{ __('querybuilder::messages.pdf') }}</option>
                                <option value="xlsx" {{ optional($scheduled_reports)->format == 'xlsx' ? 'selected' : '' }}>{{ __('querybuilder::messages.xlsx') }}</option>
                                <option value="csv" {{ optional($scheduled_reports)->format == 'csv' ? 'selected' : '' }}>{{ __('querybuilder::messages.csv') }}</option>
                            </select>
                            <span class="text-danger error" id="format-error" ></span>
                        </div>


                        <div class="col-md-4 mb-3">
                            <label for="record_limit" class="form-label">{{ __('querybuilder::messages.record_limit') }}</label>
                            <input type="number" min="1" name="record_limit" value="{{ $scheduled_reports->record_limit ?? '' }}" class="form-control" placeholder="e.g., 1000">
                            <span class="text-danger error" id="record_limit-error" ></span>
                        </div>

                         <div class="col-md-12 mb-3">
                            <label for="body" class="form-label">{{ __('querybuilder::messages.email_body') }}</label>
                            <div class="email-body">
                                <textarea name="body" class="form-control" id="email-body" rows="4" placeholder="">{!! optional($scheduled_reports)->body !!}</textarea>
                            </div>
                            <span class="text-danger error" id="body-error" ></span>
                        </div>

                        {{-- Active --}}
                        <div class="col-md-4 form-check ms-3 mb-3">
                            <input type="checkbox" class="form-check-input" {{ optional($scheduled_reports)->active == 1 ? 'checked' : '' }} name="active" id="active" value="1">
                            <label for="active" class="form-check-label">{{ __('querybuilder::messages.active') }}</label>
                        </div>
                    </div>

                    </form>
                </div>
		 	 </div>
		 </div>
	</div>
</div>
@section('scripts')
@include('wc_querybuilder::scripts.scheduling-reports-scripts')
@endsection