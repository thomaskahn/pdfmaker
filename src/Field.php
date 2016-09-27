<?php
namespace PdfMaker;

class Field
{
	const ALIGN_LEFT = 'L',
		ALIGN_CENTER = 'C',
		ALIGN_RIGHT = 'R';

	public $name, $x, $y, $width, $height, $font,
		$fontStyle, $fontSize, $textAlign, $textColor,
		$lineHeight, $value = '', $border;


	/**
	 * Field constructor.
	 *
	 * @param array $propertiesArray
	 */
	public function __construct($propertiesArray = array())
	{
		if (is_array($propertiesArray)) {
			foreach ($propertiesArray as $key => $value) {
				$this->$key = $value;
			}
		} else {
			Message::debug(__METHOD__. ' invalid argument propertiesArray:',$propertiesArray);
		}
		if (!$this->name) {
			$this->name = uniqid();
		}
	}

	public function __clone()
	{
		// when cloning, a new unique name must be given
		$this->name = uniqid();
	}

	public function setValue($value)
	{
		$this->value = $value;
		return $this;
	}

	/**
	 * Sets missing values using the values from the provided object.
	 *
	 * @param $defaults
	 */
	public function setDefaults($defaults)
	{
		foreach (get_object_vars($this) as $fieldName => $fieldValue) {
			if (empty($fieldValue)) {
				$this->$fieldName = $defaults->$fieldName;
			}
		}
	}

	public function getColorRgb()
	{
		return sscanf($this->textColor, "#%02x%02x%02x");
	}


}