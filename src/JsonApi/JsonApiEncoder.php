<?php declare(strict_types = 1);

/**
 * JsonApiEncoder.php
 *
 * @license        More in license.md
 * @copyright      https://www.fastybird.com
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        FastyBird:NodeWebServer!
 * @subpackage     JsonApi
 * @since          0.1.0
 *
 * @date           24.03.20
 */

namespace FastyBird\NodeWebServer\JsonApi;

use Neomerx\JsonApi\Encoder;

/**
 * Extended Json:API encoder
 *
 * @package        FastyBird:NodeWebServer!
 * @subpackage     JsonApi
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class JsonApiEncoder extends Encoder\Encoder
{

	/**
	 * @param object|iterable<mixed>|null $data
	 *
	 * @return mixed[]
	 */
	public function encodeDataAsArray($data): array
	{
		return $this->encodeDataToArray($data);
	}

}
