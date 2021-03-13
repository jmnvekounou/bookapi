<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ParametersControllerTest extends WebTestCase
{
    public function testIndexParameters()
    {
    	$client = static::createClient();

    	$client->request(
    			'GET',
    			'/parameters/'
    	);


        $this->assertEquals(200, $client->getResponse()->getStatusCode());
    }

}
