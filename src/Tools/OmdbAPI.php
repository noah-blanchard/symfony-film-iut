<?php

namespace App\Tools;


use Symfony\Contracts\HttpClient\HttpClientInterface;

class OmdbAPI
{

    private HttpClientInterface $client;
    private string $url;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
        $this->url = "http://www.omdbapi.com/?apikey=21bede04";
    }

    public function getDescriptionByName(string $name)
    {

        $request = $this->url . "&t=" . $name;

        $response = $this->client->request(
            'GET',
            $request
        );

        $content = $response->toArray();

        var_dump($content);

        if ($content["Response"] === "False") {
            
            return null;
        } else {
            return $content["Plot"];
        }
    }
}
