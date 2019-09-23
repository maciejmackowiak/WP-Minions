<?php

namespace WpMinions\RabbitMQ;


class Connection {

	private $connection;
	private $channel;
	private $queue;

	/**
	 * Init and test connection
	 */
	public function __construct() {
		global $rabbitmq_server, $rabbitmq_servers;

		if ( class_exists( '\PhpAmqpLib\Connection\AMQPStreamConnection' ) ) {
			if ( empty( $rabbitmq_server ) ) {
				$rabbitmq_server = array();
			}
			if ( !empty( $rabbitmq_servers )  ) {
				$rabbitmq_servers['queue'] = ($rabbitmq_servers['queue']) ? $rabbitmq_servers['queue'] : 'wordpress';
				if(!empty($rabbitmq_servers['servers']) && is_array($rabbitmq_servers['options'])){
					$this->connection = \PhpAmqpLib\Connection\AMQPStreamConnection::create_connection($rabbitmq_servers['servers'], $rabbitmq_servers['options']);
					$this->queue      = $rabbitmq_servers['queue'];
				}
			} 
			
			if(!$this->connection){
				$rabbitmq_server = wp_parse_args( $rabbitmq_server, array(
					'host'     => 'localhost',
					'port'     => 5672,
					'username' => 'guest',
					'password' => 'guest',
					'vhost'    => '/',
					'queue'    => 'wordpress'
				) );
				$this->connection = new \PhpAmqpLib\Connection\AMQPStreamConnection( $rabbitmq_server['host'], $rabbitmq_server['port'], $rabbitmq_server['username'], $rabbitmq_server['password'], $rabbitmq_server['vhost'] );
				$this->queue      = $rabbitmq_server['queue'];
			}

			$this->channel    = $this->connection->channel();
			$rabbitmq_declare_passive_filter    = apply_filters( 'wp_minion_rabbitmq_declare_passive_filter', false );
			$rabbitmq_declare_durable_filter    = apply_filters( 'wp_minion_rabbitmq_declare_durable_filter', true );
			$rabbitmq_declare_exclusive_filter  = apply_filters( 'wp_minion_rabbitmq_declare_exclusive_filter', false );
			$rabbitmq_declare_autodelete_filter = apply_filters( 'wp_minion_rabbitmq_declare_autodelete_filter', false );

			$this->channel->queue_declare( $this->queue, $rabbitmq_declare_passive_filter, $rabbitmq_declare_durable_filter, $rabbitmq_declare_exclusive_filter, $rabbitmq_declare_autodelete_filter );
			add_action( 'shutdown', array( $this, 'shutdown' ) );
		} else {
			throw new \Exception( 'Could not create connection.' );
		}
	}

	/**
	 * Return connection channel
	 *
	 * @return \PhpAmqpLib\Channel\AMQPChannel
	 */
	public function get_channel() {
		return $this->channel;
	}

	/**
	 * Return connection queue
	 *
	 */
	public function get_queue() {
		return $this->queue;
	}

	/**
	 * Close connection and channel if they are created
	 */
	public function shutdown() {
		if ( empty( $this->connection ) || empty( $this->channel ) ) {
			return;
		}
		// catch errors in case connection closed or node died
		try{
			$this->channel->close();
			$this->connection->close();
		} catch (\Throwable $e) {
			return false;
		}
	}
}
