<?php

namespace ColineServices;


/**
 * PMineAPI
 *
 * @author Alexey
 */
class PMineAPI {
    private $url = "https://pmine.ru/api/method/";
    public function method($method, $params){
        $result =  json_decode($this->curl_get_contents($this->url.$method."?". http_build_query($params)), true);
        if(is_null($result)){
            $result = [];
            $result['error'] = "Failed to connect to pmine Server";
        }
           return $result;
    }
    public function curl_get_contents($url){
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        $data = curl_exec($curl);
        curl_close($curl);
        return $data;
    }
}
