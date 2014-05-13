<?php namespace Dingo\Api\Http;

use RuntimeException;
use Dingo\Api\Transformer\Factory;
use Illuminate\Support\Collection;
use Illuminate\Support\MessageBag;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Contracts\JsonableInterface;
use Illuminate\Support\Contracts\ArrayableInterface;
use Illuminate\Http\Response as IlluminateResponse;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class Response extends IlluminateResponse {

	/**
	 * Array of registered formatters.
	 * 
	 * @var array
	 */
	protected static $formatters = [];

	/**
	 * Transformer factory instance.
	 * 
	 * @var \Dingo\Api\Transformer\Factory
	 */
	protected static $transformer;

	/**
	 * Make an API response from an existing Illuminate response.
	 * 
	 * @param  \Illuminate\Http\Response  $response
	 * @return \Dingo\Api\Http\Response
	 */
	public static function makeFromExisting(IlluminateResponse $response)
	{
		return new static($response->getOriginalContent(), $response->getStatusCode(), $response->headers->all());
	}

	/**
	 * Morph the response into the given format.
	 * 
	 * @return \Dingo\Api\Http\Response
	 */
	public function morph($format = 'json')
	{
		$response = $this->getOriginalContent();

		// For responses that are transformable we'll let the registered transformer
		// do its thing with the response. This happens prior to any formatting
		// so that a more flexible structure can be attained. Formatters are
		// merely classes that convert an array into the proper format.
		if (static::$transformer->transformableResponse($response))
		{
			$response = static::$transformer->transformResponse($response);
		}

		$formatter = static::getFormatter($format);

		// If the response is a collection and it's empty we'll let the formatter
		// decide on how it should treat an empty response.
		if ($this->responseIsCollection($response) and $response->isEmpty())
		{
			$response = $formatter->formatEmptyCollection($response);
		}

		// If a transformer is not being used then the we'll let the formatter
		// deal with the formatting of both Eloquent models and collections.
		elseif ($response instanceof EloquentModel)
		{
			$response = $formatter->formatEloquentModel($response);
		}
		elseif ($response instanceof EloquentCollection)
		{
			$response = $formatter->formatEloquentCollection($response);
		}
		else
		{
			// If we don't have an Eloquent model or collection then we'll
			// need to format the response based on whether or not it's
			// a string or an array. If it's neither then the formatter
			// must attempt to format an unknown response. This is
			// should never really happen. We should get arrays.
			if (is_array($response) or $response instanceof ArrayableInterface)
			{
				$response = $formatter->formatArrayableInterface($response);
			}
			elseif (is_string($response))
			{
				$response = $formatter->formatString($response);
			}
			else
			{
				$response = $formatter->formatUnknown($response);
			}
		}

		// Set the "Content-Type" header of the response to that which
		// is defined by the formatter being used.
		$this->headers->set('content-type', $formatter->getContentType());

		// Directly set the property because using setContent results in
		// the original content also being updated.
		$this->content = $response;

		return $this;
	}

	/**
	 * Determine if the response is a collection or paginator instance.
	 * 
	 * @param  mixed  $response
	 * @return bool
	 */
	protected function responseIsCollection($response)
	{
		return $response instanceof Collection or $response instanceof Paginator;
	}

	/**
	 * Get the formatter based on the requested format type.
	 * 
	 * @param  string  $format
	 * @return \Dingo\Api\Http\Format\FormatInterface
	 * @throws \RuntimeException
	 */
	public static function getFormatter($format)
	{
		if ( ! isset(static::$formatters[$format]))
		{
			throw new RuntimeException('Response formatter "'.$format.'" has not been registered.');
		}

		return static::$formatters[$format];
	}

	/**
	 * Set the response formatters.
	 * 
	 * @param  array  $formatters
	 * @return void
	 */
	public static function setFormatters(array $formatters)
	{
		static::$formatters = $formatters;
	}

	/**
	 * Set the transformer factory instance.
	 * 
	 * @param  \Dingo\Api\Transformer\Factory  $transformer
	 * @return void
	 */
	public static function setTransformer(Factory $transformer)
	{
		static::$transformer = $transformer;
	}

	/**
	 * Get the transformer factory instance.
	 * 
	 * @return \Dingo\Api\Transformer\Factory
	 */
	public static function getTransformer()
	{
		return static::$transformer;
	}

}
