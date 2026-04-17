<?php

namespace App\Services;

use Aws\Ses\SesClient;
use Aws\Exception\AwsException;
use Illuminate\Support\Facades\Log;

/**
 * AWS SES 郵件服務
 */
class AwsSesService
{
    protected $client;

    public function __construct()
    {
        $this->client = new SesClient([
            'version' => 'latest',
            'region'  => config('services.ses.region', 'ap-northeast-1'),
            'credentials' => [
                'key'    => config('services.ses.key'),
                'secret' => config('services.ses.secret'),
            ],
        ]);
    }

    /**
     * 執行實際的發信動作 (SES API 呼叫)
     *
     * @param string|array $to 收件者
     * @param string $subject 主旨
     * @param string $body 內容 (HTML)
     * @param string|null $from 寄件者 (預設使用 config 中的設定)
     * @return string|bool 成功回傳 MessageId，失敗回傳 false
     */
    public function send($to, $subject, $body, $from = null)
    {
        $from = $from ?: config('mail.from.address');

        try {
            $result = $this->client->sendEmail([
                'Destination' => [
                    'ToAddresses' => is_array($to) ? $to : [$to],
                ],
                'Message' => [
                    'Body' => [
                        'Html' => [
                            'Charset' => 'UTF-8',
                            'Data' => $body,
                        ],
                    ],
                    'Subject' => [
                        'Charset' => 'UTF-8',
                        'Data' => $subject,
                    ],
                ],
                'Source' => $from,
            ]);

            return $result->get('MessageId');
        } catch (AwsException $e) {
            Log::error("AWS SES Send Error: " . $e->getAwsErrorMessage(), [
                'to' => $to,
                'subject' => $subject
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error("AWS SES General Error: " . $e->getMessage());
            return false;
        }
    }
}
