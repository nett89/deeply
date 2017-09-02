<?php

namespace ChrisKonnertz\DeepLy\Protocol;

/**
 * JSON RPC is a remote procedure call protocol that uses JSOn to encode data.
 * This class represents this protocol.
 *
 * @see https://en.wikipedia.org/wiki/JSON-RPC
 */
class JsonRpcProtocol implements ProtocolInterface
{

    /**
     * The number of the supported protocol version
     */
    const PROTOCOL_VERSION = '2.0';

    /**
     * Creates a request bag according to the JSON RPC protocol.
     * The API will be able to understand it.
     * The result is encoded as a JSON string.
     *
     * @param array        $payload The payload / parameters of the request. Will be encoded as JSON
     * @param null|string  $method  The method of the API call. Null = default
     * @return string
     */
    public function createRequestData(array $payload, $method = null)
    {
        if (is_null($method)) {
            $method = 'LMT_handle_jobs';
        }
        if (! is_string($method)) {
            throw new \InvalidArgumentException('The $method argument has to be null or of type string');
        }

        $data = [
            'jsonrpc' => self::PROTOCOL_VERSION, // Set the protocol version
            'method' => $method, // Set the method of the JSON RPC API call
            'params' => $payload // Set the parameters / the payload
        ];

        $jsonData = json_encode($data);

        return $jsonData;
    }

    /**
     * Processes the data from an response from the server to an API call.
     * Returns the payload (data) of the response or throws a ProtocolException.
     *
     * @param string $rawResponseData The data (payload) of the response as a stringified JSON string
     * @return \stdClass The data (payload) of the response as an object structure
     * @throws ProtocolException
     */
    public function processResponseData($rawResponseData)
    {
        if (! is_string($rawResponseData)) {
            throw new \InvalidArgumentException('The $rawResponseData argument has to be a string');
        }

        $responseData = json_decode($rawResponseData);

        $this->validateResponseData($responseData);

        // We only return the inner result object, the other properties are no longer important
        return $responseData->result;
    }

    /**
     * Validates the response data (usually a \stdClass built by json_decode)
     * is valid response data from an API call to the DeepL API using the JSON RPC protocol.
     *
     * @param $responseData
     * @throws ProtocolException
     */
    protected function validateResponseData($responseData)
    {
        if (! $responseData instanceof \stdClass) {
            throw new ProtocolException('DeepLy API call did not return JSON that describes a \stdClass object');
        }

        if (! property_exists($responseData, 'jsonrpc')) {
            throw new ProtocolException('
                The given response data does not seem to be come from a JSON RPC request - it has no "jsonrpc" property'
            );
        }
        if ($responseData->jsonrpc !== self::PROTOCOL_VERSION) {
            throw new ProtocolException(
                'The version of the JSON RPC response does not match the expected version '.self::PROTOCOL_VERSION
            );
        }

        if (property_exists($responseData, 'error')) {
            if ($responseData->error instanceof \stdClass and property_exists($responseData->error, 'message')) {
                throw new ProtocolException(
                    'DeepLy API call resulted in this error: '.$responseData->error->message
                );
            } else {
                throw new ProtocolException('DeepLy API call resulted in an unknown error');
            }
        }

        if (! property_exists($responseData, 'result')) {
            throw new ProtocolException(
                'DeepLy API call resulted in a malformed result - inner result property is missing'
            );
        }
        if (! $responseData->result instanceof \stdClass) {
            throw new ProtocolException(
                'DeepLy API call resulted in a malformed result - inner result property is not a \stdClass'
            );
        }
    }

}