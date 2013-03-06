<?php

namespace Wrep\Notificare\Apns;

abstract class SslSocket
{
	// Socket read/write constants
	const SEND_INTERVAL = 10000; // microseconds, so this equals 0.1 seconds
	const READ_TIMEOUT = 1000000; // microseconds, so this equals 1.0 seconds

	// Settings of the connection
	private $certificate;
	private $connectTimeout;
	private $connection;

	/**
	 * Construct Connection
	 *
	 * @param $certificate Certificate The certificate to use when connecting
	 */
	public function __construct(Certificate $certificate)
	{
		// Save the given parameters
		$this->certificate = $certificate;
		$this->connectTimeout = ini_get('default_socket_timeout');

		// Setup the current state
		$this->connection = null;
	}

	/**
	 * Get the certificate used with this connection
	 *
	 * @return Certificate
	 */
	public function getCertificate()
	{
		return $this->certificate;
	}

	/**
	 * Get the SSL connection resource
	 *
	 * @return resource|null
	 */
	protected function getConnection()
	{
		return $this->connection;
	}

	/**
	 * Open the connection
	 */
	protected function connect($endpointType = Certificate::ENDPOINT_TYPE_GATEWAY)
	{
		// Create the SSL context
		$streamContext = stream_context_create();
		stream_context_set_option($streamContext, 'ssl', 'local_cert', $this->certificate->getPemFile());

		if ($this->certificate->hasPassphrase()) {
			stream_context_set_option($streamContext, 'ssl', 'passphrase', $this->certificate->getPassphrase());
		}

		// Open the connection
		$errorCode = $errorString = null;
		$this->connection = @stream_socket_client($this->certificate->getEndpoint($endpointType), $errorCode, $errorString, $this->connectTimeout, STREAM_CLIENT_CONNECT, $streamContext);

		// Check if the connection succeeded
		if (false == $this->connection)
		{
			$this->connection = null;

			// Set a somewhat more clear error message on error 0
			if (0 == $errorCode) {
				$errorString = 'Error before connecting, please check your certificate and passphrase.';
			}

			throw new \UnexpectedValueException('Failed to connect to ' . $this->certificate->getEndpoint() . ' with error #' . $errorCode . ' "' . $errorString . '".');
		}

		// Set stream in non-blocking mode and make writes unbuffered
		stream_set_blocking($this->connection, 0);
		stream_set_write_buffer($this->connection, 0);
	}

	/**
	 * Disconnect from the endpoint
	 */
	protected function disconnect()
	{
		// Check if there is a socket to disconnect
		if (is_resource($this->connection))
		{
			// Disconnect and unset the connection variable
			fclose($this->connection);
		}

		$this->connection = null;
	}
}