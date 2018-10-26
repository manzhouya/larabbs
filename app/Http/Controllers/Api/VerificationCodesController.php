<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\VerificationCodeRequest;
use Illuminate\Http\Request;
use Overtrue\EasySms\EasySms;

class VerificationCodesController extends Controller
{
    public function store(VerificationCodeRequest $request, EasySms $easySms)
    {
        $phone = $request->phone;

        // 生成 4 为随机数，左侧补 0
        $code = str_pad(random_int(1, 9999), 4, 0, STR_PAD_LEFT);

        if (!app()->environment('production')) {
            $code = '1234';
        } else {
            try {
                $reult = $easySms->send($phone, [
                    'template' => '192197',
                    'data' => [$code, 10],
                ]);
            } catch (\Overtrue\EasySms\Exceptions\NoGatewayAvailableException $exception) {
                $message = $exception->getException('qcloud')->getMessage();
                return $this->response->errorInternal($message ?? '短信发送异常');
            }

            // 缓存验证码 10 分钟过期。
            $key = 'verificationCode_' . str_random(15);
            $expiredAt = now()->addMinutes(10);
            \Cache::put($key, ['phone' => $phone, 'code' => $code], $expiredAt);

            return $this->response->array([
                'key' => $key,
                'expired_at' => $expiredAt->toDateTimeString(),
            ])->setStatusCode(201);
        }
    }
}