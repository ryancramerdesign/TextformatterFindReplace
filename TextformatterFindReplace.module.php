<?php namespace ProcessWire;

/**
 * ProcessWire 3.x Textformatter Find/Replace
 * 
 * Copyright 2021 by Ryan Cramer // MPL 2.0
 * 
 * @property string $patterns
 * 
 */
class TextformatterFindReplace extends Textformatter implements Module, ConfigurableModule {
	
	public static function getModuleInfo() {
		return array(
			'title' => 'Find/replace', 
			'version' => 1, 
			'summary' => 'Apply find/replace patterns to formatted text or markup.', 
			'requires' => 'ProcessWire>=3.0.164',
		); 
	}

	/**
	 * Cached lines to patterns indexed by PW instance ID
	 * 
	 * @var array
	 * 
	 */
	static protected $patternsArray = array();

	/**
	 * Test mode used for testing patterns in module config
	 * 
	 * @var bool
	 * 
	 */
	protected $testMode = false;
	
	/**
	 * Results when in test mode
	 * 
	 * @var array
	 * 
	 */
	protected $testModeData = array();

	/**
	 * PCRE modifier characters for pattern detection/validation
	 * 
	 * @var array
	 * 
	 */
	protected $pcreModifiers = array('i', 'm', 's', 'x', 'A', 'D', 'S', 'U', 'X', 'J', 'u');

	/**
	 * Allowed PCRE delimiters
	 * 
	 * @var array
	 * 
	 */
	protected $pcreDelims = array('/', '!', '%', '@');

	/**
	 * Construct
	 * 
	 */
	public function __construct() {
		parent::__construct();
		$this->set('patterns', '');
	}

	/**
	 * Get pattern type from given find string
	 * 
	 * @param string $find
	 * @return string One of 'str_replace' or 'preg_replace'
	 * 
	 */
	protected function getPatternType($find) {
		
		$delim = substr($find, 0, 1);
		if(!in_array($delim, $this->pcreDelims)) return 'str_replace';

		$p2 = strrpos($find, $delim);
		if($p2 === 0) return 'str_replace'; // does not re-appear at end
		
		$modifiers = substr($find, $p2+1); // i.e. /find/is where 'is' is modifiers
		if(!strlen($modifiers)) return 'preg_replace';
		
		// check that PCRE modifiers are valid
		$valid = true;
		for($n = 0; $n < strlen($modifiers); $n++) {
			if(!in_array($modifiers[$n], $this->pcreModifiers)) $valid = false;
			if(!$valid) break;
		}
	
		return $valid ? 'preg_replace' : 'str_replace';
	}

	/**
	 * Extract and return array of pattern info from line, or return false if not a pattern line
	 * 
	 * @param string $line
	 * @return array|bool
	 * 
	 */
	protected function getPatternItemFromLine($line) {
		
		$line = trim($line);
		
		$commentPos = strrpos($line, '//');

		if($commentPos !== false && $line[$commentPos-1] !== '\\') {
			$parts = explode('//', $line);
			$comment = trim(array_pop($parts));
			$line = implode('//', $parts);
			$line = rtrim($line);
		} else {
			$comment = '';
		}
		
		if(strpos($line, '\\=')) {
			// there is an escaped equals sign
			$hasEscapeEq = true;
			$line = str_replace('\\=', '{EQUALS}', $line);
		} else {
			$hasEscapeEq = false;
		}
		
		$eqPos = strpos($line, '=');
		if($eqPos === false) return false;

		if($eqPos === strrpos($line, '=')) {
			// 1 equals sign: line has just: find=replace
			list($find, $replace) = explode('=', $line, 2);
			$check = '';
		} else {
			// line has: check=find=replace
			list($check, $find, $replace) = explode('=', $line, 3);
		}

		$check = trim($check);
		$find = trim($find);
		$replace = trim($replace);
		$type = $this->getPatternType($find);
		
		if($hasEscapeEq) {
			$check = str_replace('{EQUALS}', '=', $check);
			$find = str_replace('{EQUALS}', '=', $find);
			$replace = str_replace('{EQUALS}', '=', $replace); 
		}

		$item = array(
			'type' => $type,
			'find' => $find,
			'replace' => $replace,
			'check' => $check,
			'comment' => $comment,
		);

		return $item;
	}

	/**
	 * Get verbose array of all defined find/replace patterns
	 * 
	 * @return array
	 * 
	 */
	public function getPatternsArray() {
		
		$instanceId = $this->wire()->getProcessWireInstanceID();
		if(isset(self::$patternsArray[$instanceId])) return self::$patternsArray[$instanceId];

		$items = array();
		
		foreach(explode("\n", $this->patterns) as $line) {
			$item = $this->getPatternItemFromLine($line);
			if($item === false) continue;
			$items[] = $item;
		}
		
		self::$patternsArray[$instanceId] = $items;
		
		return $items;
	}

	/**
	 * Format the given $value 
	 * 
	 * @param string $value
	 * 
	 */
	public function format(&$value) {
		
		$patterns = $this->getPatternsArray();
		
		foreach($patterns as $key => $a) {
			
			if(strlen($a['check']) && stripos($value, $a['check']) === false) {
				$a['result'] = "Did not match preflight check: $a[check]";
				
			} else if($a['type'] === 'preg_replace') {
				$_value = $value;
				$value = preg_replace($a['find'], $a['replace'], $value);
				if($value === null) {
					$value = $_value;
					$a['result'] = "Error in regex: $a[find]";
				} else if($value === $_value) {
					$a['result'] = "Find regex did not match given text";
				} else {
					$a['result'] = "Successful $a[type]";
				}
				unset($_value);
				
			} else if(strpos($value, $a['find']) !== false) {
				$value = str_replace($a['find'], $a['replace'], $value);
				$a['result'] = "Successful $a[type]";
				
			} else {
				$a['result'] = "Find string did not match given text";
			}
			
			$patterns[$key] = $a;
		}
		
		if($this->testMode) $this->testModeData = $patterns;
	}

	/**
	 * Module config
	 * 
	 * @param InputfieldWrapper $inputfields
	 * 
	 */
	public function getModuleConfigInputfields(InputfieldWrapper $inputfields) {
		
		$sanitizer = $this->wire()->sanitizer;
		$modules = $this->wire()->modules;
		$session = $this->wire()->session;
		$input = $this->wire()->input;
		$test = $session->getFor($this, '_test'); 
		$qty = substr_count($this->patterns, "\n"); 
		
		/** @var InputfieldTextarea $f */
		$f = $modules->get('InputfieldTextarea');
		$f->attr('name', 'patterns');
		$f->label = 'Find/replace match patterns';
		$f->description = 
			'Enter one per line of: `find=replace` or `/find/=replace`, where `find` is a string to find *or* `/find/` ' . 
			'is a regular expression pattern to match, and `replace` is a replacement string. ' . 
			'Please see the [usage instructions](#) for full details and options.';
		$f->attr('style', 'font-family: monospace; white-space: nowrap'); 
		$f->val($this->patterns);
		$f->attr('rows', ($qty >= 5 ? $qty+2 : 5)); 
		$inputfields->add($f);
		
		$f = $modules->get('InputfieldTextarea');
		$f->attr('name', '_test');
		$f->label = 'Test above patterns';
		$f->description = $this->_('Enter some text or markup to text your patterns with it.');
		$f->attr('style', 'font-family: monospace'); 
		$f->val($test);
		$inputfields->add($f);
	
		if($input->requestMethod('POST')) {
			$test = $input->post('_test');
			if($test) $session->setFor($this, '_test', $test);
		} else if($test) {
			$session->removeFor($this, '_test');
			$this->testMode = true;
			$this->format($test);
			$patterns = $sanitizer->entities(print_r($this->testModeData, true));
			$test = $sanitizer->entities($test);
			$this->warning("<p><strong>Your result:</strong></p><pre style='white-space: pre-wrap'>$test</pre>", Notice::allowMarkup);
			$this->warning("<p><strong>Your find/replace patterns:</strong></p><pre>$patterns</pre>", Notice::allowMarkup);
		}
	}

}
