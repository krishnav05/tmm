@if( isset( $speakers ) && count( $speakers ) > 0 )
	<ul class="video-speaker-profile">
		@foreach( $speakers as $speaker )
		  	<li> 
		        <a href="{{ url( 'speaker/'.$speaker['slug'] ) }}" class="profile-pic float-left" title="{{ $speaker['name'] }} ">
		        	@if( !empty( $speaker['crop'] ) )
		        		<img src="{{ asset( $speaker['crop'] ) }}" alt="{{ $speaker['name'] }} " title="{{ $speaker['name'] }} " />
		        	@else
		              	<img src="{{ asset( 'frontend/images/tmm-logo2.png' ) }}" alt="{{ $speaker['name'] }} " title="{{ $speaker['name'] }} ">
		          @endif
		        </a>
		        <a href="{{ url( 'speaker/'.$speaker['slug'] ) }}" class="title" title="{{ $speaker['name'] }} ">
		        	{{ ucfirst( $speaker['name'] ) }} 
		        </a>
		        <div class="designation" title="Speaker Designation">
 		        	{{ ucfirst( $speaker['designation'] ) }}
		        </div>
		        <p>
		        	{{ $speaker['short_description'] }}
		        </p>
		    </li>
		@endforeach
	</ul>
@else
	<ul class="video-speaker-profile">
		<p>{{ __( 'messages.speaker_found' ) }}</p>
	</ul>
@endif