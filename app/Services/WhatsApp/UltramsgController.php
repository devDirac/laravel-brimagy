<?php

namespace App\Services\WhatsApp;

class UltramsgController
{
    protected $token = '';
    protected $instance_id = '';

    public function __construct($token, $instance_id)
    {
        $this->token = $token;
        $this->instance_id = "instance" . preg_replace('/[^0-9]/', '', $instance_id);
    }

    public function sendChatMessage($to, $body, $priority = 10, $referenceId = "")
    {
        $params = array(
            "to" => $to,
            "body" => $body,
            "priority" => $priority,
            "referenceId" => $referenceId
        );
        return $this->sendRequest("POST", "messages/chat", $params);
    }

    public function sendDocumentMessage($to, $filename, $document, $caption = "", $priority = 10, $referenceId = "", $nocache = false)
    {
        $params = array(
            "to" => $to,
            "filename" => $filename,
            "document" => $document,
            "caption" => $caption,
            "priority" => $priority,
            "referenceId" => $referenceId,
            "nocache" => $nocache
        );
        return $this->sendRequest("POST", "messages/document", $params);
    }

    public function sendLinkMessage($to, $link, $priority = 10, $referenceId = "")
    {
        $params = array(
            "to" => $to,
            "link" => $link,
            "priority" => $priority,
            "referenceId" => $referenceId
        );
        return $this->sendRequest("POST", "messages/link", $params);
    }

    public function sendImageMessage($to, $image, $caption = "", $priority = 10, $referenceId = "", $nocache = false)
    {
        $params = array(
            "to" => $to,
            "caption" => $caption,
            "image" => $image,
            "priority" => $priority,
            "referenceId" => $referenceId,
            "nocache" => $nocache
        );
        return $this->sendRequest("POST", "messages/image", $params);
    }

    private function sendRequest($method, $path, $params = array())
    {
        if (!is_callable('curl_init')) {
            return array("Error" => "cURL extension is disabled on your server");
        }

        $url = "https://api.ultramsg.com/" . $this->instance_id . "/" . $path;
        $params['token'] = $this->token;
        $data = http_build_query($params);

        if (strtolower($method) == "get") {
            $url = $url . '?' . $data;
        }

        $curl = curl_init($url);

        if (strtolower($method) == "post") {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }

        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, 1);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($httpCode == 404) {
            return array("Error" => "instance not found or pending please check you instance id");
        }

        $contentType = curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
        $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $body = substr($response, $header_size);
        curl_close($curl);

        if (strpos($contentType, 'application/json') !== false) {
            return json_decode($body, true);
        }

        return $body;
    }
}
