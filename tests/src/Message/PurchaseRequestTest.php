<?php

namespace ByTIC\Omnipay\Euplatesc\Tests\Message;

use ByTIC\Omnipay\Euplatesc\Message\PurchaseRequest;
use ByTIC\Omnipay\Euplatesc\Message\PurchaseResponse;
use Omnipay\Common\Exception\InvalidRequestException;
use Guzzle\Http\Client as HttpClient;

/**
 * Class PurchaseRequestTest
 * @package ByTIC\Omnipay\Euplatesc\Tests\Message
 */
class PurchaseRequestTest extends AbstractRequestTest
{
    public function testInitParameters()
    {
        $data = [
            'mid' => 222,
            'key' => 333,
            'endpointUrl' => 444,
        ];
        $request = $this->newRequestWithInitTest(PurchaseRequest::class, $data);
        self::assertEquals($data['mid'], $request->getMid());
        self::assertEquals($data['key'], $request->getKey());
        self::assertEquals($data['endpointUrl'], $request->getEndpointUrl());
    }

    public function testSendWithMissingAmount()
    {
        $data = [
            'mid' => 111,
            'key' => 333,
            'card' => [
                'first_name' => '',
            ],
            'endpointUrl' => 444,
        ];
        $request = $this->newRequestWithInitTest(PurchaseRequest::class, $data);

        self::expectException(InvalidRequestException::class);
        self::expectExceptionMessage('The amount parameter is required');
        $request->send();
    }

    public function testSend()
    {
        $data = [
            'mid' => $_ENV['EUPLATESC_MID'],
            'key' => $_ENV['EUPLATESC_KEY'],
            'orderId' => '99999897987987987987987',
            'orderName' => 'Test tranzaction 9999999999',
            'notifyUrl' => 'http://localhost',
            'returnUrl' => 'http://localhost',
            'endpointUrl' => 'https://secure.euplatesc.ro/tdsprocess/tranzactd.php',
            'card' => [
                'first_name' => '',
            ],
            'amount' => 20.00,
            'currency' => 'RON',
        ];
        $request = $this->newRequestWithInitTest(PurchaseRequest::class, $data);

        /** @var PurchaseResponse $response */
        $response = $request->send();
        self::assertInstanceOf(PurchaseResponse::class, $response);

        $redirectData = $response->getRedirectData();
        self::assertCount(17, $redirectData);

        $client = new HttpClient();
        $gatewayResponse = $client->post($response->getRedirectUrl(), null, $redirectData)->send();
        self::assertSame(200, $gatewayResponse->getStatusCode());
        self::assertStringContainsString('secure.euplatesc.ro', $gatewayResponse->getEffectiveUrl());

        //Validate first Response
        $body = strtolower($gatewayResponse->getBody(true));
        self::assertContains("<meta http-equiv='refresh' content=", $body);

        if (preg_match('/\<meta[^\>]+http-equiv=\'refresh\' content=\'.*?url=(.*?)\'/i', $body, $matches)) {
            $url = $matches[1];
            $gatewayResponse = $client->get($url)->send();
            $body = $gatewayResponse->getBody(true);
        }

        self::assertContains('Num&#259;r comand&#259;:', $body);
        self::assertContains('Descriere comand&#259;:', $body);
        self::assertContains($data['orderId'], $body);
        self::assertContains($data['orderName'], $body);
    }
}