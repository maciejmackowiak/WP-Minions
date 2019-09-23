<?php

namespace WpMinions\RabbitMQ;

use WpMinions\Worker as BaseWorker;

/**
 * The RabbitMQ Worker uses the  php-amqplib to execute Jobs in the
 * RabbitMQ queue. The servers that the Worker should connect to are
 * configured as part of the initialization.
 *
 */
class Worker extends BaseWorker {
	/**
	 * Instance of Connection class
	 *
	 * @var Connection
	 */
	public $connection = null;

	/**
	 * Connect to host and channel
	 */
	private function connect() {
		if ( null !== $this->connection ) {
			return $this->connection;
		}

		try {
			$this->connection = new Connection();
		} catch ( \Exception $e ) {
			return false;
		}

		return $this->connection;
	}

	public function register() {
		// Do nothing
	}

	public function work() {
		if ( ! $this->connect() ) {
			return false;
		}

		#https://stackoverflow.com/questions/31915773/rabbimq-what-are-ready-unacked-types-of-messages
		// if we want to process messages one by one uncomment this line otherwise worker will consume a lot of messages but still will need to acknowledge them to rabbimq
		$this->connection->get_channel()->basic_qos(null, 1, null);
		$this->connection->get_channel()->basic_consume( $this->connection->get_queue(), '', false, false, false, false, function( $message ) {
			try {
				$job_data = json_decode( $message->body, true );
				$hook     = $job_data['hook'];
				$args     = $job_data['args'];

				if ( function_exists( 'is_multisite' ) && is_multisite() && $job_data['blog_id'] ) {
					$blog_id = $job_data['blog_id'];

					if ( get_current_blog_id() !== $blog_id ) {
						switch_to_blog( $blog_id );
						$switched = true;
					} else {
						$switched = false;
					}
				} else {
					$switched = false;
				}

				do_action( 'wp_async_task_before_job', $hook, $message );
				do_action( 'wp_async_task_before_job_' . $hook, $message );

				do_action( $hook, $args, $message );

				do_action( 'wp_async_task_after_job', $hook, $message );
				do_action( 'wp_async_task_after_job_' . $hook, $message );
				// let's now our rabbitmq that message is acknowledged 
				$message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
				$result = true;
			} catch ( \Throwable $e ) {
				error_log(
					'RabbitMQWorker->do_job failed: ' . $e->getMessage()
				);
				// maybe exit on exception?
				// so the supervior will restart worker?
				// exit;
				$result = false;
			}

			if ( $switched ) {
				restore_current_blog();
			}
		} );

		while ( count( $this->connection->get_channel()->callbacks ) ) {
			$this->connection->get_channel()->wait();
		}
	}
}
