<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Validator;
use Illuminate\Support\Facades\Hash;
use Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;

class UserController extends Controller
{
    // register
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:2|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors());
        }
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);
        return response()->json([
            'message' => 'User inserted successfully!',
            'user' => $user
        ]);
    }
    // login
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:6'
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors());
        }

        if (!$token = auth()->attempt($validator->validated())) {
            return response()->json(['success' => false, 'message' => 'Email or password incorrect!']);
        }
        return $this->responseWithToken($token);
    }
    protected function responseWithToken($token)
    {
        return response()->json([
            'success' => true,
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => auth()->factory()->getTTL() * 60
        ]);
    }

    // logout
    public function logout()
    {
        try {
            auth()->logout();
            return response()->json(['success' => true, 'message' => 'User logged out!']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }
    // profile
    public function profile()
    {
        try {
            return response()->json(['success' => true, 'data' => auth()->user()]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }
    // update profile
    public function updateProfile(Request $request)
    {
        if (auth()->user()) {
            $validator = Validator::make($request->all(), [
                'id' => 'required',
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255',
            ]);
            if ($validator->fails()) {
                return response()->json($validator->errors());
            }
            $user = User::find($request->id);
            $user->name = $request->name;
            $user->email = $request->email;
            $user->save();
            return response()->json(['success' => true, 'message' => 'Updated user successfully!', 'data' => $user]);
        } else {
            return response()->json(['success' => false, 'message' => 'User is not Authenticated.']);
        }
    }
    // send verify email
    public function sendVerifyMail($email)
    {
        if (auth()->user()) {
            $user = User::where('email', $email)->get();
            if (count($user) > 0) {
                $random = Str::random(40);
                $domain = URL::to('/');
                $url = $domain . '/verify-mail/' . $random;
                $data['url'] = $url;
                $data['email'] = $email;
                $data['title'] = "Email verification!";
                $data['body'] = "Please click here to below to verify your email.";
                Mail::send('verifyMail', ['data' => $data], function ($message) use ($data) {
                    $message->to($data['email'])->subject($data['title']);
                });
                $user = User::find($user[0]['id']);
                $user->remember_token = $random;
                $user->save();
                return response()->json(['success' => true, 'message' => 'Mail sent successfully.']);
            } else {
                return response()->json(['success' => false, 'message' => 'User not found!']);
            }
        } else {
            return response()->json(['success' => false, 'message' => 'User is not Authenticated.']);
        }
    }
    public function verificationMail($token)
    {
        $user = User::where('remember_token', $token)->get();
        if (count($user) > 0) {
            $datetime = Carbon::now()->format('Y-m-d H:i:s');
            $user = User::find($user[0]['id']);
            $user->remember_token = '';
            $user->email_verified_at = $datetime;
            $user->save();
            // return response()->json(['success' => true,'message' => 'Email vefiry successfully!']);
            return "<h1>Email verified successfully</h1>";
        } else {
            return view('404');
            // return response()->json(['success' => false,'message' => 'User not found!']);
        }
    }
    // refresh token
    public function refreshToken()
    {
        if (auth()->user()) {
            return $this->responseWithToken(auth()->refresh());
        } else {
            return response()->json(['success' => false, 'message' => 'User is not Authenticated.']);
        }
    }
}
