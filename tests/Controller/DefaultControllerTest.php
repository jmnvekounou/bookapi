<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DefaultControllerTest extends WebTestCase
{
    public function testIndexParameters()
    {
    	$client = static::createClient();

    	$client->request(
    			'GET',
    			'/'
    	);

die;
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
    }

}