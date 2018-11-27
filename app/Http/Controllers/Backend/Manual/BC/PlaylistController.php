<?php

namespace App\Http\Controllers\Backend\Manual\BC;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Lang;
use App\Models\Playlist;
use App\Models\PlaylistMeta;
use DB;

class PlaylistController extends Controller {

	protected function guard() {
	    return Auth::guard('admin');
	}
    

    /**
     * get playlist response using cURL from Brightcove
     *
     * @return Assoc Array
     */
    private function getcURLResponsePlaylist() {
        /**
         * proxy for Brightcove RESTful APIs
         * gets an access token, makes the request, and returns the response
         * Accessing:
         *         (note you should *always* access the proxy via HTTPS)
         *     Method: POST
         *     request body (accessed via php://input) is a JSON object with the following properties
         *
         * {string} url - the URL for the API request
         * {string} [requestType=GET] - HTTP method for the request
         * {string} [requestBody] - JSON data to be sent with write requests
         * {string} [client_id] - OAuth2 client id with sufficient permissions for the request
         * {string} [client_secret] - OAuth2 client secret with sufficient permissions for the request
         *
         * if client_id, client_secret, or account_id are not included in the request, default values will be used
         *
         * @returns {string} $response - JSON response received from the API
         */

        /** 
            security checks
            if you want to do some basic security checks, such as checking the origin of the
            the request against some white list, this is the place to do it
            CORS enablement and other headers
        
        header("Access-Control-Allow-Origin: *");
        header("Content-type: application/json");
        header("X-Content-Type-Options: nosniff");
        header("X-XSS-Protection");
        */
        /**
            default account values
            if you work on one Brightcove account, put in the values below
            if you do not provide defaults, the client id, and client secret must
            be sent in the request body for each request
        */
        $account_id     =   env('account_id');
        $client_id      =   env('cms_playlist_client_id');
        $client_secret  =   env('cms_playlist_client_secret');
        $request_url    =   env('cms_playlist_url').$account_id.'/playlists';

        $auth_string    = "{$client_id}:{$client_secret}";
        $request_oauth  = env('oauth_url')."?grant_type=client_credentials";
        $ch             = curl_init($request_oauth);
        curl_setopt_array($ch, array(
            CURLOPT_POST           => TRUE,
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_SSL_VERIFYPEER => FALSE,
            CURLOPT_USERPWD        => $auth_string,
            CURLOPT_HTTPHEADER     => array(
                'Content-type: application/x-www-form-urlencoded',
            ),
        ));

        $response = curl_exec($ch);
        curl_close($ch);

        // Check for errors
        if ($response === FALSE) {
            $message = "Error: there was a problem with your Oauth API call" + curl_error($ch);
            Log::debug($message);
            die();
        }

        // Decode the response
        $responseData = json_decode($response, TRUE);
        $access_token = $responseData["access_token"];

        // get request type or default to GET
        $method = "GET";

        /**
            more security checks
            optional: you might want to check the URL for the API request here
            and make sure it is to an approved API
            and that there is no suspicious code appended to the URL

            get the URL and authorization info from the form data
            send the http request
        */
        
        $ch = curl_init($request_url);
            curl_setopt_array($ch, array(
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_SSL_VERIFYPEER => FALSE,
            CURLOPT_HTTPHEADER     => array(
              'Content-type: application/json',
              "Authorization: Bearer {$access_token}",
            )
        ));
        $response = curl_exec($ch);
        curl_close($ch);

        /**
            Check for errors and log them if any
            note that logging will fail unless
            the file log.txt exists in the same
            directory as the proxy and is writable
        */
        if ($response === FALSE) {
            $message = "Error: there was a problem with your CMS API call" + curl_error($ch);
            Log::debug($message);
            die();
        }

        /**
            Decode the response
            $responseData = json_decode($response, TRUE);
            return the response to the AJAX caller
            TRUE will convert STD Object to assoc Array
        */
        $responseDecoded = json_decode($response, TRUE);
        if (!isset($responseDecoded)) {
            $response = '{null}';
        }

        return $responseDecoded;
    }

     /* Execute the console command.
     *
     * @return mixed
     */
    public function managePlaylistFromBC() {

        $getcURLResponse = $this->getcURLResponsePlaylist();

        /*
            Count Total playlist Fetch from brightcove via CMS API
        */
        $totalPlaylist = count($getcURLResponse);

        /*
            Condition if > 0 then add into db;
            else show msg 
        */
        if($totalPlaylist > 0) {

            for($i=0; $i<$totalPlaylist; $i++) {

                $playlist_data = $getcURLResponse[$i];

                $this->conditionOnPlaylistAddition( $playlist_data);
            }

        } else {
            return redirect('/admin/dashboard')->with('status', Lang::get('messages.playlist_not_found'));
        }

        return redirect('/admin/dashboard')->with('status', Lang::get('messages.managed_playlist'));
    }

    /*
        check conditions like: slug, name and id
    */
    private function conditionOnPlaylistAddition( $data) {

        $checkPlaylistIDIfExists = $this->checkPlaylistIDIfAlreadyExists( $data['id'] );

        if($checkPlaylistIDIfExists == 0) { 

            $this->addPlaylist( $data );
            $this->addPlaylistMeta( $data );

        }   else {

            /*
                Update data from brightcove
                @getting updated_at value;
            */
            $updated_at = $data['updated_at'];

            /*
                Value taking from database using video id;
                @getting updated_at value from db;
            */
            $checkUpdate_at = $this->getPlaylistUpdatedAt( $data['id'] );

            if( $checkUpdate_at != $updated_at) {

                $this->deleteMetaPlaylistUsingID( $data['id'] );
                $this->addPlaylist( $data );
                $this->addPlaylistMeta( $data );
            }
        }
    }

    /*
        Check Playlist ID; 
        If already exists;
    */
    private function checkPlaylistIDIfAlreadyExists( $playlist_id ) {

        /*
            count records using Playlist id
        */

        $countPlaylist     = Playlist::where( 'id', $playlist_id )->count() ?? '0';

        return $countPlaylist;
    } 

    /*
        Check if slug is already exists;
    */
    private function checkSlugIfAlreadyExists( $slug ) {
        /*
            Retrive or check records using playlist id
        */

        $countSlug  = Playlist::where('slug', $slug)->count() ?? '0';

        return $countSlug;
    }

    /**
     * Prepare a date for array / JSON serialization.
     *
     * @param  \DateTime  $date
     * @return string
     */
    private function getFormatedDate( $value ) {
        $date = Carbon::parse($value)->format('Y-m-d H:i:s');
        return $date;
    }

    /**
        Add Playlist;
    */
    private function addPlaylist( $data ) {

        /**
            Current Time Using Carbon;
        */
        $current_time   =   Carbon::now()->toDateTimeString();

        /**
            Create playlist Slug using str_slug
        */
        $slug = str_slug( $data['name'], '-' );

        /**
            Check if slug is already created for any playlist;
        */
        $checkSlugIfExists = $this->checkSlugIfAlreadyExists( $slug );

        if($checkSlugIfExists == 0) {

            $p_name = $data['name'];
            $p_slug = $slug;

        } else {

            $p_name = $data['name'].'-'.md5(uniqid(rand(), true));
            $p_slug = str_slug( $p_name, '-' );

        }

        Playlist::create([ 
            'id'            =>  $data['id'],
            'name'          =>  $p_name,
            'slug'          =>  $p_slug,
            'created_at'    =>  $current_time ,
            'updated_at'    =>  $current_time,
        ]);
    }

    /**
        Add Playlist Metas
    */
    private function addPlaylistMeta( $data ) {

        foreach( $data as $key => $value ) {

            $playlist_id    =   $data['id'];
            $current_time   =   Carbon::now()->toDateTimeString();
            
            if(is_array($value)) {

                $meta_value = $this->encode( $value );

                $this->addMetaPlaylistToDB( $playlist_id, $key, $meta_value, $current_time );
            } else {
                $this->addMetaPlaylistToDB( $playlist_id, $key, $value, $current_time );
            }
        }    
    } 

    /**
        Encode the array data in serialize with encoded in json;
    */
    private function encode($data) {
        return serialize(json_decode(json_encode($data), true));
    }

    /**
        Adding Video Meta to DB;
    */
    private function addMetaPlaylistToDB( $id, $key, $value, $time ) {
        DB::table('playlist_metas')->insert([  
            [   
                'playlist_id'   =>  $id, 
                'meta_key'      =>  $key,   
                'meta_value'    =>  $value,  
                'created_at'    =>  $time, 
                'updated_at'    =>  $time
            ],
        ]);
    }

    /*
        get meta_key => 'udpated_at' value
    */ 
    private function getPlaylistUpdatedAt( $id ) {
        $queryPlaylistMeta = PlaylistMeta::where( 'playlist_id', $id )
                                    ->where( 'meta_key', 'updated_at' )
                                    ->select( 'meta_value' )
                                    ->first(); 
        return $queryPlaylistMeta['meta_value'];
    }

    /*
        @Delete Meta using video_id
    */
    private function deleteMetaPlaylistUsingID( $id ) {
        Playlist::destroy( $id );
        PlaylistMeta::where( 'playlist_id', $id )->delete();
    }
}
