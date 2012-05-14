<?php

App::uses('StripeAppModel', 'Stripe.Model');
App::uses('StripeCustomer', 'Stripe.Model');
App::uses('HttpSocket', 'Network/Http');

class TestStripeModel extends StripeAppModel {
	
	public $path = '/action';
	
}

class StripeSourceTest extends CakeTestCase {
	
	public function setUp() {
		parent::setUp();
		ConnectionManager::loadDatasource(array(
			'plugin' => 'Stripe',
			'classname' => 'StripeSource'
		));
		
		$this->Source = new StripeSource(array(
			'api_key' => '123456'
		));
		$this->Source->Http = $this->getMock('HttpSocket', array('request'));
		$this->Model = new TestStripeModel();
	}
	
	public function tearDown() {
		parent::tearDown();
		unset($this->Source);
		unset($this->Model);
	}
	
	public function testReformat() {
		$model = new StripeCustomer();
		
		$data = array(
			'number' => '234',
			'name' => 'Jeremy',
			'email' => 'jeremy@42pixels.com',
			'address_line_1' => '123 Main'
		);
		$result = $this->Source->reformat($model, $data);
		$expected = array(
			'card' => array(
				'number' => '234',
				'name' => 'Jeremy',
				'address_line_1' => '123 Main'
			),
			'email' => 'jeremy@42pixels.com'
		);
		$this->assertEqual($result, $expected);
		
		$model->formatFields['user'] = array('email');
		$data = array(
			'number' => '234',
			'name' => 'Jeremy',
			'email' => 'jeremy@42pixels.com',
			'address_line_1' => '123 Main'
		);
		$result = $this->Source->reformat($model, $data);
		$expected = array(
			'card' => array(
				'number' => '234',
				'name' => 'Jeremy',
				'address_line_1' => '123 Main'
			),
			'user' => array(
				'email' => 'jeremy@42pixels.com'
			)
		);
		$this->assertEqual($result, $expected);
	}
	
	public function testConstructWithoutKey() {
		$this->setExpectedException('CakeException');
		$source = new StripeSource();
	}
	
	public function testRequest() {
		$this->Source->Http->response = array(
			'status' => array(
				'code' => '404',
			),
			'body' => '{}'
		);
		$this->Source->Http->expects($this->at(0))
			->method('request')
			->will($this->returnValue($this->Source->Http->response['body']));
		$response = $this->Source->request(array('uri' => array('path' => '/path/')));
		$this->assertFalse($response);
		$this->assertEqual($this->Source->lastError, 'Unexpected error.');
		$this->assertEqual($this->Source->request['uri']['path'], '/v1/path');
		
		$this->Source->Http->response = array(
			'status' => array(
				'code' => '402',
			),
			'body' => '{"error":{ "message" : "This is an error message"}}'
		);
		$this->Source->Http->expects($this->at(0))
			->method('request')
			->will($this->returnValue($this->Source->Http->response['body']));
		$response = $this->Source->request();
		$this->assertFalse($response);
		$this->assertEqual($this->Source->lastError, 'This is an error message');
		
		$this->Source->Http->response = array(
			'status' => array(
				'code' => '200',
			),
			'body' => '{"id" : "123"}'
		);
		$this->Source->Http->expects($this->at(0))
			->method('request')
			->will($this->returnValue($this->Source->Http->response['body']));
		$response = $this->Source->request();
		$this->assertNull($this->Source->lastError);
		$this->assertEqual($response, array('id' => '123'));
	}
	
	public function testCreate() {
		$this->Source->Http->response = array(
			'status' => array('code' => 200),
			'body' => '{"object" : "customer", "id" : "1234"}'
		);
		$this->Source->Http->expects($this->at(0))
			->method('request')
			->will($this->returnValue($this->Source->Http->response['body']));
		$response = $this->Source->create($this->Model, array('email', 'description'), array('jeremy@42pixels.com', 'Jeremy Harris'));
		$this->assertTrue($response);
		$this->assertEqual($this->Source->request['method'], 'POST');
		$this->assertEqual($this->Model->getLastInsertId(), 1234);
		$this->assertEqual($this->Source->request['body'], array(
			'email' => 'jeremy@42pixels.com',
			'description' => 'Jeremy Harris'
		));
	}
	
	public function testRead() {
		$this->Source->Http->response = array(
			'status' => array('code' => 200),
			'body' => '{"object" : "customer", "id" : "1234", "description" : "Jeremy Harris"}'
		);
		$this->Source->Http->expects($this->at(0))
			->method('request')
			->will($this->returnValue($this->Source->Http->response['body']));
		$response = $this->Source->read($this->Model, array('conditions' => array('TestStripeModel.id' => '1234')));
		$this->assertEqual($response, array(
			array(
				'TestStripeModel' => array(
					'id' => '1234',
					'object' => 'customer',
					'description' => 'Jeremy Harris'
				)
			)
		));
		$this->assertEqual($this->Model->id, 1234);
		$this->assertEqual($this->Source->request['method'], 'GET');
		$this->assertEqual($this->Source->request['uri']['path'], '/v1/action/1234');
	}
	
	public function testUpdate() {
		$this->Source->Http->response = array(
			'status' => array('code' => 200),
			'body' => '{"object" : "customer", "id" : "1234"}'
		);
		$this->Source->Http->expects($this->at(0))
			->method('request')
			->will($this->returnValue($this->Source->Http->response['body']));
		$response = $this->Source->update($this->Model, array('email', 'description', 'id'), array('jeremy@42pixels.com', 'Jeremy Harris', '1234'));
		$this->assertEquals(array(
			'TestStripeModel' => array(
				'object' => 'customer',
				'id' => '1234',
			),
		), $response);
		$this->assertEqual($this->Model->id, 1234);
		$this->assertEqual($this->Source->request['body'], array(
			'email' => 'jeremy@42pixels.com',
			'description' => 'Jeremy Harris'
		));
		$this->assertEqual($this->Source->request['method'], 'POST');
		$this->assertEqual($this->Source->request['uri']['path'], '/v1/action/1234');
	}
	
	public function testDelete() {
		$this->Source->Http->response = array(
			'status' => array('code' => 200),
			'body' => '{"deleted" : "true", "id" : "1234"}'
		);
		$this->Source->Http->expects($this->at(0))
			->method('request')
			->will($this->returnValue($this->Source->Http->response['body']));
		$response = $this->Source->delete($this->Model, array('TestStripeModel.id' => '1234'));
		$this->assertTrue($response);
		$this->assertEqual($this->Source->request['method'], 'DELETE');
		$this->assertEqual($this->Source->request['uri']['path'], '/v1/action/1234');
	}
	
}