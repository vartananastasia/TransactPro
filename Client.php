<?php

namespace TransactPro;

use GuzzleHttp\Client as GC;
use HiloadHandler\HiloadHandlerProcess as HH;


/**
 * Class Client
 * @package SPSR
 */
class Client
{
    /**
     * @var string
     */
    private $login;

    /**
     * @var string
     */
    private $password;

    /**
     * @var bool
     */
    private $test;

    # Settings for saving transact id
    const entity_transact = 10;

    # API settings
    # for TEST
    const test_api_base_url = 'https://gw2sandbox.tpro.lv:8443/gw2test/gwprocessor2.php?a=init';
    const test_api_success_url = 'https://gw2sandbox.tpro.lv:8443/gw2test/gwprocessor2.php?a=status_request';
    const test_login = 'JBX***';
    const test_password = 'x174****';

    # for REAL
    const api_base_url = 'https://www2.1stpayments.net/gwprocessor2.php?a=init';
    const api_success_url = 'https://www2.1stpayments.net/gwprocessor2.php?a=status_request';


    /**
     * Client constructor.
     * @param $login
     * @param $password
     * @param bool $test
     */
    public function __construct($login, $password, $test = False)
    {
        if ($test) {
            $this->login = self::test_login;
            $this->password = sha1(self::test_password);
        }
        else{
            $this->login = $login;
            $this->password = sha1($password);
        }
        $this->test = $test;
    }


    /**
     * @param array $fields
     */
    public function Execute(array $fields = [
        'rs' => 'PT02',
        'merchant_transaction_id' => '1234567765432',
        'user_ip' => '194.87.11.58',
        'description' => 'site.com order 44',
        'amount' => '444000',
        'currency' => 'RUB',
        'name_on_card' => 'test name',
        'street' => 'test street',
        'zip' => 'test postcode',
        'city' => 'test city',
        'country' => 'RU',
        'state' => 'NA',
        'email' => 'test@test.ru',
        'phone' => '79000000000',
        'merchant_site_url' => 'https://site.com',
        'custom_return_url' => 'https://site.com/ru/my/?merchant_transaction_id=ZZZZZZZ'
    ])
    {
        if ($this->test)
            $url = self::test_api_base_url;
        else
            $url = self::api_base_url;

        $fields['merchant_transaction_id'] = rand(100000, 10000000000);

        $body = "guid={$this->login}&pwd={$this->password}&";
        foreach ($fields as $key => $val)
        {
            $val = trim($val);
            if ($val)
            {
                $body .= $key . '=' . $val . '&';
            }
        }
        $body = substr($body,0,-1);

        $client = new GC();
        $response = $client->request('POST', $url,
            ["headers" =>
                ['Content-Type'=>'application/x-www-form-urlencoded'],
                'body' => $body
            ]);
        $data = $response->getBody();
        $res = explode('~RedirectOnsite:', $data);
        $trans_id = substr($res[0], 3); # into hh

        $entity_class_transact = HH::GetHLEntityClass(self::entity_transact);
        $entity_class_transact::add(
            [
                'UF_TRANS_ID' => $trans_id,
                'UF_PELITT_ID' => $fields["merchant_transaction_id"],
            ]
        );

        $link = $res[1]; # redirect to link
        LocalRedirect($link, true);
    }


    public function Success()
    {
        $entity_class_transact = HH::GetHLEntityClass(self::entity_transact);
        $rsData = $entity_class_transact::getList(array(
            "select" => array("*"),
            "order" => array("ID" => "ASC"),
            "filter" => array('UF_PELITT_ID' => $_GET["merchant_transaction_id"])
        ));
        while ($data = $rsData->Fetch()){
            $trans_id = $data["UF_TRANS_ID"];
            break;
        }
        if ($trans_id)
            return self::GetStatus($trans_id);
        else
            return false;
    }


    public function GetStatus($transaction_id)
    {
        if ($this->test)
            $url = self::test_api_success_url;
        else
            $url = self::api_success_url;

        $body = "guid={$this->login}&pwd={$this->password}&request_type=transaction_status&init_transaction_id=" . $transaction_id;

        $client = new GC();
        $response = $client->request('POST', $url,
            ["headers" =>
                ['Content-Type'=>'application/x-www-form-urlencoded'],
                'body' => $body
            ]);
        $data = $response->getBody();
        $res = explode(':', $data);
        if ($res[1] == 'Success')
            return true;
        else
            return false;
    }
}