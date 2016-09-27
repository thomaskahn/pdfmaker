<?php
namespace PdfMaker;

use Log;

/**
 * Class Pdf
 * Easily create PDFs from existing PDFs, or new PDFs from scratch.
 * Add positionable fields to the PDF and dynamically set their values, then write the result.
 *
 * todo create a class PdfPage and use it to store the page-related data.
 */
class Pdf
{
	// todo change most of these to protected members

	/** @var \FPDI|\TCPDF */
	public $pdf = null;

	/** @var bool force borders to be drawn for all objects. */
	public $drawBorders = false;

	/** @var Field The default values for unspecified values of fields. */
	public $fieldDefaults;

	/** @var array fields drawn to the PDF, indexed by page. field names must be unique per page. */
	public $fields = array();

	/** @var array PDF files to be imported. The index determines the order in which the PDFs are imported into the resulting document. */
	protected $sourcePdfFiles = array();

	protected $_totalPages = 0;

	/** @var int Assuming the same margins for every page of the document. */
	public $pageMarginX = 0, $pageMarginY = 0;

	const DEFAULTS = array('font'       => 'Helvetica',
						   'lineHeight' => 1,
						   'fontSize'   => 12,
						   'border'     => 0,
						   'textColor'  => '#000000',
						   'textAlign'  => Field::ALIGN_LEFT);
	const PORTRAIT = 1;

	/**
	 * Pdf constructor.
	 *
	 * @param null  $importFile
	 * @param array $defaults Allows one or more default settings to be specified.
	 * @see Pdf::DEFAULTS
	 */
	public function __construct($importFile = null, $defaults = [])
	{
		$defaults = array_merge(self::DEFAULTS, $defaults);
		$this->setFieldDefaults($defaults);

		$this->pdf = new \FPDI('P', 'in', 'letter');

		if ($importFile) {
			$this->addPagesFromFile($importFile);
		}
	}


	/**
	 * Specify render-time default properties for all fields.
	 *
	 * @param array $defaultProperties
	 */
	public function setFieldDefaults($defaultProperties)
	{
		$this->fieldDefaults = new Field($defaultProperties);
	}

	/**
	 * @see addPagesFromFile
	 * @deprecated
	 * @param $pdfFileName
	 */
	public function setSourcePdf($pdfFileName)
	{
		$this->addPagesFromFile($pdfFileName);
	}

	public function addPagesFromFile($filename)
	{
		$this->sourcePdfFiles[] = $filename;
		$this->_totalPages += $this->pdf->setSourceFile($filename);
	}

	/**
	 * For each of the $fieldValues, $filename is added to the document and a new $field is created. Page breaking is disabled. The height property of $field determines the
	 * vertical offset to apply for each new field added. A page-break is added after the last $fieldValues element. Only the first page from the $filename document is imported.
	 *
	 * @param             $filename
	 * @param array|Field $field
	 * @param array       $fieldValues
	 */
	public function addPageAndField($filename, $field, $fieldValues, $pageBreakAfterEachField = false)
	{
		$pageX     = $this->pageMarginX;
		$originalY = $field->y;
		$pageY     = 999; // force a page to be created in the first iteration of loop
		$this->pdf->setSourceFile($filename);
		$template = $this->pdf->importPage(1, '/CropBox');
		$size = $this->pdf->getTemplateSize($template);
		if (!($field instanceof Field)) {
			$field = new Field($field);
		}

		foreach ($fieldValues as $codeIndex => $value) {
			if ($pageBreakAfterEachField || $pageY + $size['h'] >= 11) {
//				Log::debug("$codeIndex . adding page break (break = $pageBreakAfterEachField, " . ($pageY + $size['h']));
				$pageY    = $this->pageMarginY;
				$field->y = $originalY;
				$this->pdf->AddPage();
			}
//			Log::debug("$codeIndex . putTemplate at $pageX, $pageY, render at {$field->x},{$field->y}");
			$this->pdf->useTemplate($template, $pageX, $pageY);
			$field->value = $value;
			$this->_renderField($field);

			$field->y = $field->y + $size['h'];
			$pageY    = $pageY + $size['h'];
		}
	}


	public function getField($name, $pageNumber = null)
	{
		if ($pageNumber !== null) {
			return $this->fields[$pageNumber][$name];
		} else {
			foreach ($this->fields as $pageNumber => $fields) {
				if (isset($fields[$name])) {
					return $fields[$name];
				}
			}
		}
	}

	/**
	 * Add a field to a specific page. If $pageNumber = null, it is added to the last page of the currently-defined document.
	 *
	 * @param Field|array $field If an array, it will be cast to a Field object first.
	 * @param null        $pageNumber
	 * @return Field A reference to the newly-added field.
	 * @throws \Exception
	 */
	public function addField($field, $pageNumber = null)
	{
		if ($pageNumber === null) {
			$pageNumber = max(0, $this->_totalPages - 1);
		}

		if (!($field instanceof Field)) {
			$field = new Field($field);
		}
		$this->fields[$pageNumber][$field->name] = $field;
		return $this->fields[$pageNumber][$field->name];
	}


	/**
	 * Draw text inside a bounding box.
	 *
	 * @deprecated
	 * @param        $string
	 * @param        $x
	 * @param        $y
	 * @param        $width
	 * @param        $height
	 * @param string $align
	 */
	public function drawTextBox($string, $x, $y, $width, $height, $align = Field::ALIGN_LEFT)
	{
		$this->pdf->SetFont('Helvetica', '', 10);
		$this->pdf->SetTextColor(0, 0, 0);
		$this->pdf->SetXY($x, $y);
		$this->pdf->Cell($width, $height, $string, $this->drawBorders, 0, $align, 0);
	}

	/**
	 * Draws a field onto the PDF.
	 *
	 * @param Field $field
	 */
	protected function _renderField(Field $field)
	{
		$field->setDefaults($this->fieldDefaults);
		$border     = $this->drawBorders ? true : $field->border;
		$fill       = 0;
		$lineHeight = 0;
		$fontSize   = floatval($field->fontSize);
		$fontStyle  = $field->fontStyle;
		$this->pdf->SetFont($field->font, $fontStyle, $fontSize);
		$color = $field->getColorRgb();
		$this->pdf->SetTextColor($color[0], $color[1], $color[2]);
		if ($field->width && $field->height) {
			$this->pdf->SetXY($field->x, $field->y);
			$this->pdf->Cell($field->width, $field->height, $field->value, $border, $lineHeight, $field->textAlign, 0, null, 0, false, 'T', 'T');

//			height = height of each line (not total height of cell)
//			$this->pdf->MultiCell($field->width, $field->height,$field->value,$border,$field->textAlign,$fill);
		} else {
			$this->pdf->Text($field->x, $field->y, $field->value);
		}
	}

	/**
	 * Saves the resulting PDF with all pages imported and all fields drawn to it.
	 *
	 * @param string|null $filename If null, a direct download is produced.
	 */
	public function save($filename = null)
	{
		$pageNumber = 0;
		foreach ($this->sourcePdfFiles as $pdfFileName) {
			$pages = $this->pdf->setSourceFile($pdfFileName);
			for ($importPageNumber = 1; $importPageNumber <= $pages; $importPageNumber++) {
				$template = $this->pdf->importPage($importPageNumber, '/CropBox');
				$size     = $this->pdf->getTemplateSize($template);
				$this->pdf->AddPage();
				$this->pdf->useTemplate($template, $this->pageMarginX, $this->pageMarginY);
				if (isset($this->fields[$pageNumber])) {
					array_walk($this->fields[$pageNumber], array($this, '_renderField'));
				}
				$pageNumber++;
			}
		}
//		$this->pdf->SetAutoPageBreak(0);
		if ($filename === null) {
			$this->pdf->Output('prize-form.pdf', 'I');
		} else {
			$this->pdf->Output(realpath($filename), 'F');
		}
	}

	public function outputToBrowser()
	{
		$this->pdf->Output('prize-form.pdf', 'I');
	}

	public static function pdfToImage($inputFile, $outputFile)
	{
		$cmd = "magick  -density 300 -quality 100    \"$inputFile\"  \"$outputFile\"";
		exec($cmd);
		return self::getPdfImages($outputFile);
	}

	public static function getPdfImages($filename)
	{
		$ext  = substr($filename, -4);
		$mask = str_replace($ext, "-*{$ext}", $filename);
		return glob($mask);
	}
}