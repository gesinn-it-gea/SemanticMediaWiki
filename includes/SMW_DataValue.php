<?php

/**
 * Objects of this type represent all that is known about
 * a certain user-provided data value, especially its various
 * representations as strings, tooltips, numbers, etc.
 *
 * @note AUTOLOADED
 */
abstract class SMWDataValue {

	protected $m_property = false;    /// The text label of the respective property or false if none given
	protected $m_caption;             /// The text label to be used for output or false if none given
	protected $m_errors = array();    /// Array of error text messages
	protected $m_isset = false;       /// True if a value was set.
	protected $m_typeid;              /// The type id for this value object
	protected $m_infolinks = array(); /// Array of infolink objects
	protected $m_outformat = false;   /// output formatting string, see setOutputFormat()

	private $m_hasssearchlink;        /// used to control the addition of the standard search link
	private $m_hasservicelinks;       /// used to control service link creation

	public function SMWDataValue($typeid) {
		$this->m_typeid = $typeid;
	}

///// Set methods /////

	/**
	 * Set the user value (and compute other representations if possible).
	 * The given value is a string as supplied by some user. An alternative
	 * label for printout might also be specified.
	 */
	public function setUserValue($value, $caption = false) {
		wfProfileIn('SMWDataValue::setUserValue (SMW)');
		$this->m_errors = array(); // clear errors
		$this->m_infolinks = array(); // clear links
		$this->m_hasssearchlink = false;
		$this->m_hasservicelinks = false;
		if ( is_string($caption) ) {
			$this->m_caption = trim($caption);
		} else {
			$this->m_caption = false;
		}
		$this->parseUserValue($value); // may set caption if not set yet, depending on datavalue
		$this->m_isset = true;
		if ($this->isValid()) {
			$this->checkAllowedValues();
		}
		wfProfileOut('SMWDataValue::setUserValue (SMW)');
	}

	/**
	 * Set the xsd value (and compute other representations if possible).
	 * The given value is a string that was provided by getXSDValue() (all
	 * implementations should support round-tripping).
	 */
	public function setXSDValue($value, $unit = '') {
		wfProfileIn('SMWDataValue::setXSDValue (SMW)');
		$this->m_errors = array(); // clear errors
		$this->m_infolinks = array(); // clear links
		$this->m_hasssearchlink = false;
		$this->m_hasservicelinks = false;
		$this->m_caption = false;
		$this->parseXSDValue($value, $unit);
		$this->m_isset = true;
		wfProfileOut('SMWDataValue::setXSDValue (SMW)');
	}

	/**
	 * Set the property to which this value refers. Used to generate search links and
	 * to find custom settings that relate to the property.
	 * The property is given as a simple wiki text title, without namespace prefix.
	 */
	public function setProperty($propertyname) {
		$this->m_property = $propertyname;
	}

	public function addInfoLink(SMWInfoLink $link) {
		$this->m_infolinks[] = $link;
	}

	/**
	 * Servicelinks are special kinds of infolinks that are created from current parameters
	 * and in-wiki specification of URL templates. This method adds the current property's
	 * servicelinks found in the messages. The number and content of the parameters is
	 * depending on the datatype, and the service link message is usually crafted with a
	 * particular datatype in mind.
	 */
	function addServiceLinks() {
		if ($this->m_hasservicelinks) return;
		$args = $this->getServiceLinkParams();
		if ($args === false) return; // no services supported
		array_unshift($args, ''); // add a 0 element as placeholder
		$ptitle = Title::newFromText($this->m_property, SMW_NS_PROPERTY);
		$servicelinks = array();
		if ( $ptitle !== NULL ) {
			$servicelinks = smwfGetStore()->getSpecialValues($ptitle, SMW_SP_SERVICE_LINK);
		}

		foreach ($servicelinks as $dvs) {
			$args[0] = 'smw_service_' . str_replace(' ', '_', $dvs); // messages distinguish ' ' from '_'
			$text = call_user_func_array('wfMsgForContent', $args);
			$links = preg_split("([\n][\s]?)", $text);
			foreach ($links as $link) {
				$linkdat = explode('|',$link,2);
				if (count($linkdat) == 2)
					$this->addInfolink(SMWInfolink::newExternalLink($linkdat[0],trim($linkdat[1])));
			}
		}
		$this->m_hasservicelinks = true;
	}

	/**
	 * Define a particular output format. Output formats are user-supplied strings
	 * that the datavalue may (or may not) use to customise its return value. For
	 * example, quantities with units of measurement may interpret the string as
	 * a desired output unit. In other cases, the output format might be built-in
	 * and subject to internationalisation (which the datavalue has to implement).
	 * In any case, an empty string resets the output format to the default.
	 */
	public function setOutputFormat($formatstring) {
		$this->m_outformat = $formatstring; // just store it, subclasses may or may not use this
	}

	/**
	 * Add a new error string to the error list. All error string must be wiki and
	 * html-safe! No further escaping will happen!
	 */
	public function addError($errorstring) {
		$this->m_errors[] = $errorstring;
	}

///// Abstract processing methods /////

	/**
	 * Initialise the datavalue from the given value string.
	 * The format of this strings might be any acceptable user input
	 * and especially includes the output of getWikiValue().
	 */
	abstract protected function parseUserValue($value);

	/**
	 * Initialise the datavalue from the given value string and unit.
	 * The format of both strings strictly corresponds to the output
	 * of this implementation for getXSDValue() and getUnit().
	 */
	abstract protected function parseXSDValue($value, $unit);

///// Get methods /////

	/**
	 * Returns a short textual representation for this data value. If the value
	 * was initialised from a user supplied string, then this original string
	 * should be reflected in this short version (i.e. no normalisation should
	 * normally happen). There might, however, be additional parts such as code
	 * for generating tooltips. The output is in wiki text.
	 *
	 * The parameter $linked controls linking of values such as titles and should
	 * be non-NULL and non-false if this is desired.
	 */
	abstract public function getShortWikiText($linked = NULL);

	/**
	 * Returns a short textual representation for this data value. If the value
	 * was initialised from a user supplied string, then this original string
	 * should be reflected in this short version (i.e. no normalisation should
	 * normally happen). There might, however, be additional parts such as code
	 * for generating tooltips. The output is in HTML text.
	 *
	 * The parameter $linker controls linking of values such as titles and should
	 * be some Linker object (or NULL for no linking).
	 */
	abstract public function getShortHTMLText($linker = NULL);

	/**
	 * Return the long textual description of the value, as printed for
	 * example in the factbox. If errors occurred, return the error message
	 * The result always is a wiki-source string.
	 *
	 * The parameter $linked controls linking of values such as titles and should
	 * be non-NULL and non-false if this is desired.
	 */
	abstract public function getLongWikiText($linked = NULL);

	/**
	 * Return the long textual description of the value, as printed for
	 * example in the factbox. If errors occurred, return the error message
	 * The result always is an HTML string.
	 *
	 * The parameter $linker controls linking of values such as titles and should
	 * be some Linker object (or NULL for no linking).
	 */
	abstract public function getLongHTMLText($linker = NULL);

	/**
	 * Returns a short textual representation for this data value. If the value
	 * was initialised from a user supplied string, then this original string
	 * should be reflected in this short version (i.e. no normalisation should
	 * normally happen). There might, however, be additional parts such as code
	 * for generating tooltips. The output is in the specified format.
	 *
	 * The parameter $linker controls linking of values such as titles and should
	 * be some Linker object (for HTML output), or NULL for no linking.
	 */
	public function getShortText($outputformat, $linker = NULL) {
		switch ($outputformat) {
			case SMW_OUTPUT_WIKI: return $this->getShortWikiText($linker);
			case SMW_OUTPUT_HTML: default: return $this->getShortHTMLText($linker);
		}
	}

	/**
	 * Return the long textual description of the value, as printed for
	 * example in the factbox. If errors occurred, return the error message.
	 * The output is in the specified format.
	 *
	 * The parameter $linker controls linking of values such as titles and should
	 * be some Linker object (for HTML output), or NULL for no linking.
	 */
	public function getLongText($outputformat, $linker = NULL) {
		switch ($outputformat) {
			case SMW_OUTPUT_WIKI: return $this->getLongWikiText($linker);
			case SMW_OUTPUT_HTML: default: return $this->getLongHTMLText($linker);
		}
	}

	/**
	 * Return text serialisation of info links. Ensures more uniform layout 
	 * throughout wiki (Factbox, Property pages, ...).
	 */
	public function getInfolinkText($outputformat, $linker=NULL) {
		$result = '';
		$first = true;
		$extralinks = array();
		switch ($outputformat) {
		case SMW_OUTPUT_WIKI:
			foreach ($this->getInfolinks() as $link) {
				if ($first) {
					$result .= '<!-- -->&nbsp;&nbsp;' . $link->getWikiText();
						// the comment is needed to prevent MediaWiki from linking URL-strings together with the nbsps!
					$first = false;
				} else {
					$extralinks[] = $link->getWikiText();
				}
			}
			break;
		case SMW_OUTPUT_HTML:
			foreach ($this->getInfolinks() as $link) {
				if ($first) {
					$result .= '&nbsp;&nbsp;' . $link->getHTML($linker);
					$first = false;
				} else {
					$extralinks[] = $link->getHTML($linker);
				}
			}
			break;
		}
		if (count($extralinks) > 0) {
			$result .= smwfEncodeMessages($extralinks, 'info', ', <!--br-->');
		}
		return $result;
	}

	/**
	 * Return the XSD compliant version of the value, or FALSE if parsing the 
	 * value failed and no XSD version is available. If the datatype has units, 
	 * then this value is given in the unit provided by getUnit().
	 */
	abstract public function getXSDValue();

	/**
	 * Return the plain wiki version of the value, or
	 * FALSE if no such version is available. The returned
	 * string suffices to reobtain the same DataValue
	 * when passing it as an input string to setUserValue().
	 * Thus it also includes units, if any.
	 */
	abstract public function getWikiValue();

	/**
	 * Return the numeric representation of the value, or FALSE
	 * is none is available. This representation is used to
	 * compare values of scalar types more efficiently, especially
	 * for sorting queries. If the datatype has units, then this
	 * value is to be interpreted wrt. the unit provided by getUnit().
	 * Possibly overwritten by subclasses.
	 */
	public function getNumericValue() {
		return NULL;
	}

	/**
	 * Return the unit in which the returned value is to be interpreted.
	 * This string is a plain UTF-8 string without wiki or html markup.
	 * Returns the empty string if no unit is given for the value.
	 * Possibly overwritten by subclasses.
	 */
	public function getUnit() {
		return ''; // empty unit
	}

	/**
	 * Return a short string that unambiguously specify the type of this value.
	 * This value will globally be used to identify the type of a value (in spite
	 * of the class it actually belongs to, which can still implement various types).
	 */
	public function getTypeID() {
		return $this->m_typeid;
	}

	/**
	 * Return an array of SMWLink objects that provide additional resources
	 * for the given value.
	 * Captions can contain some HTML markup which is admissible for wiki
	 * text, but no more. Result might have no entries but is always an array.
	 */
	public function getInfolinks() {
		global $smwgIP;
		include_once($smwgIP . '/includes/SMW_Infolink.php');
		if ($this->isValid() && $this->m_property) {
			if (!$this->m_hasssearchlink) { // add default search link
				$this->m_hasssearchlink = true;
				$this->m_infolinks[] = SMWInfolink::newPropertySearchLink('+', $this->m_property, $this->getWikiValue());
			}
			if (!$this->m_hasservicelinks) { // add further service links
				$this->addServiceLinks();
			}
		}
		return $this->m_infolinks;
	}

	/**
	 * Overwritten by callers to supply an array of parameters that can be used for 
	 * creating servicelinks. The number and content of values in the parameter array
	 * may vary, depending on the concrete datatype.
	 */
	protected function getServiceLinkParams() {
		return false;
	}

	/**
	 * Return a string that identifies the value of the object, and that can
	 * be used to compare different value objects.
	 * Possibly overwritten by subclasses (e.g. to ensure that returned value is
	 * normalised first)
	 */
	public function getHash() {
		if ($this->isValid()) { // assume that XSD value + unit say all
			return $this->getXSDValue() . $this->getUnit();
		} else {
			return implode("\t", $this->m_errors);
		}
	}

	/**
	 * Return TRUE if values of the given type generally have a numeric version.
	 * Possibly overwritten by subclasses.
	 */
	public function isNumeric() {
		return false;
	}

	/**
	 * Return TRUE if a value was defined and understood by the given type,
	 * and false if parsing errors occured or no value was given.
	 */
	public function isValid() {
		return ( (count($this->m_errors) == 0) && $this->m_isset );
	}

	/**
	 * Return a string that displays all error messages as a tooltip, or
	 * an empty string if no errors happened.
	 */
	public function getErrorText() {
		return smwfEncodeMessages($this->m_errors);
	}

	/**
	 * Return an array of error messages, or an empty array
	 * if no errors occurred.
	 */
	public function getErrors() {
		return $this->m_errors;
	}

	/**
	 * Create an SMWExpData object that encodes the given data value in an exportable
	 * way. This representation is used by exporters, e.g. to be further decomposed into
	 * RDF triples or to generate OWL/XML serialisations.
	 * If the value is empty or invalid, NULL is returned.
	 */
	public function getExportData() { // default implementation: encode value as untyped string
		if ($this->isValid()) {
			$lit = new SMWExpLiteral(smwfHTMLtoUTF8($this->getXSDValue()), $this);
			return new SMWExpData($lit);
		} else {
			return NULL;
		}
	}

	/**
	 * Check if property is range restricted and, if so, whether the current value is allowed.
	 * Creates an error if the value is illegal.
	 */
	protected function checkAllowedValues() {
		if ($this->m_property === false) return; // allowed values apply only to concrete properties
		$ptitle = Title::newFromText($this->m_property, SMW_NS_PROPERTY);
		if ($ptitle === NULL) return;
		$allowedvalues = smwfGetStore()->getSpecialValues($ptitle, SMW_SP_POSSIBLE_VALUE);
		if (count($allowedvalues) == 0) return;
		$hash = $this->getHash();
		$value = SMWDataValueFactory::newTypeIDValue($this->getTypeID());
		$accept = false;
		$valuestring = '';
		foreach ($allowedvalues as $stringvalue) {
			$value->setUserValue($stringvalue->getXSDValue());
			if ($hash === $value->getHash()) {
				$accept = true;
				break;
			} else {
				if ($valuestring != '') {
					$valuestring .= ', ';
				}
				$valuestring .= $value->getShortWikiText();
			}
		}
		if (!$accept) {
			$this->addError(wfMsgForContent('smw_notinenum', $this->getWikiValue(), $valuestring));
		}
	}

}


