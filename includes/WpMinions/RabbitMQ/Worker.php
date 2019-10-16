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

		// we want to process messages one by one
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
				// acknowledge message so it will be removed from the queue
				$message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
				$result = true;
			} catch ( \Throwable $e ) {
				$job_data = json_decode( $message->body, true );
				$hook     = $job_data['hook'];
				$args     = $job_data['args'];
				// log error with job details
				error_log(
					'RabbitMQWorker->do_job failed: ' . $e->getMessage() . "\n".
					'stack trace: ' . $e->getTraceAsString() . "\n".
					'hook: ' . $hook . "\n".
					'args: ' . print_r($args, true)
				);
				// acknowledge message so it will be removed from the queue we need to remove it from the queue to prevent creating endless loop when for example there is an php error in the task

				// @TODO: consider trying to run this job once again in case worker just died doing the job
				// but after second time we need to remove it from the queue
				$message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
				$result = false;
				exit;
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
