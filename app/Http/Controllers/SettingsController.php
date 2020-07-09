<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Exception\RequestException;
use function GuzzleHttp\json_encode;

class SettingsController extends Controller
{
    // Defining headers
    public $headers;
    public $user_id;

    // Controller action to display settings page.
    public function index()
    {

        // Setting header
        $user_details = [];
        Cookie::get('user_id') !== null ?  $user_details['id'] = Cookie::get('user_id') : "Not set";
        Cookie::get('email') !== null ?  $user_details['email'] = Cookie::get('email') : "Not set";
        Cookie::get('first_name') !== null ?  $user_details['first_name'] = Cookie::get('first_name') : "Not set";
        Cookie::get('last_name') !== null ?  $user_details['last_name'] = Cookie::get('last_name') : "Not set";
        Cookie::get('phone_number') !== null ?  $user_details['phone_number'] = Cookie::get('phone_number') : "Not set";
        Cookie::get('is_active') !== null ?  $user_details['is_active'] = Cookie::get('is_active') : "Not set";
        return view('backend.settings.settings')->with("user_details", $user_details);
        // Setting User_id
        $this->user_id = Cookie::get('user_id');
    }

    // Controller action to update user details.
    public function update(Request $request)
    {

        // Setting header

        // Setting User_id
        $this->user_id = Cookie::get('user_id');

        try {
            // check if all fields are available
            if ($request->all()) {
                $control = $request->input('control', '');

                if ($control == 'profile_update') {

                    $url = env('API_URL', 'https://dev.api.customerpay.me') . '/store-admin/update';
                    $client = new Client();
                    $phone_number = intval($request->input('phone_number'));
                    $data = [
                        "first_name" => $request->input('first_name'),
                        "last_name" => $request->input('last_name'),
                        "email" => $request->input('email'),
                        "phone_number" => $phone_number
                    ];
                    // make an api call to update the user_details
                    $this->headers = ['headers' => ['x-access-token' => Cookie::get('api_token')], 'form_params' => $data];
                    $form_response_process = $client->request('PUT', $url, $this->headers);
                } elseif ($control == 'password_change') {
                    $validator = Validator::make($request->all(), [
                        'new_password' => 'required|regex:/^(?=.*\d)(?=.*[a-z]).{6,20}$/|confirmed',
                    ]);
                    if ($validator->fails()) {
                        $request->session()->flash('alert-class', 'alert-danger');
                        $request->session()->flash('message', "password update failed, invalid input");
                        return redirect()->route('change_password');
                    } else {
                        $url = env('API_URL', 'https://dev.api.customerpay.me') . '/update-password';
                        $client = new Client();
                        $data = [
                            "oldPassword" => $request->input('current_password'),
                            "newPassword" => $request->input('new_password'),
                            "confirmPassword" => $request->input('new_password_confirmation')
                        ];
                        // make an api call to update the user_details
                        $this->headers = ['headers' => ['x-access-token' => Cookie::get('api_token')], 'form_params' => $data];
                        $form_response_process = $client->request('POST', $url, $this->headers);
                    }
                } else {
                    return view('errors.404');
                }
                if ($form_response_process->getStatusCode() == 500) {
                    return view('errors.500');
                }
                if ($form_response_process->getStatusCode() == 400) {
                    if ($control == 'profile_update') {
                        $response = json_decode($form_response_process->getBody(), true);
                        $message = $response->error->description;
                        return view('backend.settings.settings')->with("user_details", $message);
                    }
                }
                if ($form_response_process->getStatusCode() == 401) {
                    $response = json_decode($form_response_process->getBody(), true);
                    $message = $response->error->description;
                    $request->session()->flash('alert-class', 'alert-success');
                    $request->session()->flash('message', "Password updated successfully");
                    if ($control == 'profile_update') {
                        return view('backend.settings.settings');
                    }
                    if ($control == 'password_change') {
                        return redirect()->route('change_password');
                    }
                }
                if ($form_response_process->getStatusCode() == 200) {
                    $request->session()->flash('alert-class', 'alert-success');
                    if ($control == 'profile_update') {
                        $user_detail_res = json_decode($form_response_process->getBody(), true);
                        $filtered_user_detail = $user_detail_res['data']['store_admin']['local'];
                        $user_details = [
                            "email" => $filtered_user_detail['email'],
                            "phone_number" => $filtered_user_detail['phone_number'],
                            "first_name" => $filtered_user_detail['first_name'],
                            "last_name" => $filtered_user_detail['last_name'],
                            "is_active" => Cookie::get('is_active')
                        ];
                        Cookie::queue('phone_number', $filtered_user_detail['phone_number']);
                        Cookie::queue('first_name', $filtered_user_detail['first_name']);
                        Cookie::queue('email', $filtered_user_detail['email']);
                        Cookie::queue('last_name', $filtered_user_detail['last_name']);
                        $request->session()->flash('message', "Profile details updated successfully");
                        return redirect()->route('setting')->with("user_details", $user_details);
                    }
                    if ($control == 'password_change') {
                        $request->session()->flash('message', "Password updated successfully");
                        return redirect()->route('change_password');
                    }
                }
            } else {
                return redirect()->route('settings');
            }
        } catch (RequestException $e) {
            $request_res = json_decode($e->getResponse()->getBody());
            $request->session()->flash('alert-class', 'alert-danger');
            $request->session()->flash('message', $request_res->message);
            if ($control == 'password_change') {
                return redirect()->route('change_password');
            }
            if ($control == 'profile_update') {
                return redirect()->route('setting');
            }
        }
    }

    public function change_password()
    {
        return view('backend.change_password.index');
    }
}
