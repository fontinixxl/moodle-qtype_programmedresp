<?php

/**
 * It parses php code to extract functions and comments
 *
 * @copyright 2010 David Monllaó <david.monllao@urv.cat>
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package qtype_programmedresp
 */
class functions_tokenizer {

    protected $functions = false;
    protected $errors = false;

    protected $skippedtokens;

    function __construct() {

        $this->skippedtokens = array(
            'T_ML_COMMENT',
            'T_COMMENT',
            'T_INCLUDE_ONCE',
            'T_INCLUDE',
            'T_REQUIRE_ONCE',
            'T_REQUIRE',
           );
        $this->skippedtokens = array_flip($this->skippedtokens);

        $this->skippedfunctions = array(
            'system', 'exec', 'popen', 'shell_exec', 'passthru', 'proc_open', 'chgrp', 'chmod',
            'chown', 'copy', 'delete', 'file_put_contents', 'flock', 'fopen', 'fputs', 'fputcsv',
            'ftruncate', 'fwrite', 'lchgrp', 'lchown', 'link', 'mkdir', 'move_uploaded_file',
            'rename', 'rmdir', 'symlink', 'tempnam', 'tmpfile', 'touch', 'umask', 'unlink',
            'ini_set', 'session_destroy', 'session_unset', 'session_unregister'
        );
        $this->skippedfunctions = array_flip($this->skippedfunctions);
    }

    function set_code($code) {

        global $USER;

        $i = 0;   // Functions count
        $j = 0;   // Comments count
        $functions = array();
        $functiondata = array('');

        $tokenscode = '<?php '.$code.'?>';
        $tokens = token_get_all($tokenscode);

        if ($tokens) {
	        foreach ($tokens as $tokendata) {

	            if (!is_array($tokendata) && isset($functions[$i]) && isset($functions[$i]->functioncode)) {
	                $functions[$i]->functioncode .= $tokendata.' ';
	                continue;
	            }

	            if (!is_array($tokendata)) {
	                error($tokendata);
	            }
	            $token = token_name($tokendata[0]);

	            // PHP <? tags
	            if ($token == 'T_OPEN_TAG' || $token == 'T_OPEN_TAG_WITH_ECHO' || $token == 'T_CLOSE_TAG') {
	                continue;
	            }

	            // Skips system and/or dangerous functions
	            if ($token == 'T_STRING') {
	                if (!empty($this->skippedfunctions[$tokendata[1]])) {

                            $subject = 'Programmed responses dangerous usage';
	                    $notification = 'The user with id ' . $USER->id . ' is trying to add dangerous function/s: '.chr(13).chr(10).chr(13).chr(10);
	                    $notification.= $code;

	                    // Getting system admins
	                    $systemcontext = get_context_instance(CONTEXT_SYSTEM);
	                    $admins = get_users_by_capability($systemcontext, 'moodle/site:doanything', 'u.*');
	                    foreach ($admins as $admin) {
	                        email_to_user($admin, $admin, $subject, $notification);
	                    }
	                    print_error('errordangerousfunction', 'qtype_programmedresp');
	                }
	            }

	            // Function code
	            if ($tokendata[1] != 'php' && $token != 'T_DOC_COMMENT') {

	                // New function
	                if ($token == 'T_FUNCTION') {


//	                    if (isset($functions[$i]->description) && isset($functions[$i]->params) && isset($functions[$i]->type) &&
//	                        isset($functions[$i]->nreturns) && isset($functions[$i]->results)) {
	                        $j++;
//	                    }

	                    // Set the function key
	                    if (isset($functions[$i]->functioncode)) {
	                        $i++;
	                    }
	                    $functions[$i]->functioncode = $tokendata[1].' ';
	                    $setfunctionname = true;

	                // Code
	                } else if (isset($functions[$i]) && isset($functions[$i]->functioncode)) {

	                    // Skipped comments in $this->skippedctoken
	                    if (empty($this->skippedtokens[$token])) {
	                        $functions[$i]->functioncode .= $tokendata[1].' ';
	                    }
	                }
	            }

	            // Setting the function names
	            if (!empty($setfunctionname) && $token != 'T_FUNCTION' && $token != 'T_WHITESPACE') {
	                $functions[$i]->name = $tokendata[1];
	                $setfunctionname = false;
	            }

	            // Comments
	            if ($token == 'T_DOC_COMMENT') {

	                // Description
	                $last = strpos($tokendata[1], '@param');
	                $functions[$j]->description = str_replace('*', '', substr($tokendata[1], 1, $last - 1));

	                $data =  trim(preg_replace('/\r?\n *\* */', ' ', $tokendata[1]), '/');

	                // Params
	                preg_match_all('/@param\s+(.*?)\s*(?=$|@[a-z]+\s)/s', $data, $params);
	                if ($params) {
	                    foreach ($params[1] as $key => $match) {

	                        $matcharray = explode(' ', $match);
	                        $functions[$j]->params[$key]->type = array_shift($matcharray);
	                        array_shift($matcharray);
	                        $functions[$j]->params[$key]->description = implode(' ', $matcharray);
	                    }
	                }

	                // Parse return tag to extract return type + number of returned values + return description
	                preg_match('/@return\s+(.*?)\s*(?=$|@[a-z]+\s)/s', $data, $return);
	                if ($return) {

	                    $returnstr = rtrim(ltrim($return[1]));

	                    // Until the first space the return type
	                    $type = substr($returnstr, 0, strpos($returnstr, ' '));
	                    $functions[$j]->type = $type;

	                    // Until the second space the number of results
	                    $nreturns = substr($returnstr, (strlen($type) + 1), strpos($returnstr, ' ', strlen($type) + 1) - (strlen($type) + 1));
	                    $functions[$j]->nreturns = $nreturns;

	                    // Results descriptions
	                    $matcharray = explode('|', substr($returnstr, (strlen($type) + strlen($nreturns) + 2)));
	                    if ($matcharray) {
	                        foreach ($matcharray as $key => $description) {
	                            $functions[$j]->results[$key] = $description;
	                        }
	                    }

	                }
	            }
	        }
        }

        // Check integrity
        if ($functions) {
	        foreach ($functions as $key => $function) {

	            // Description
	            if (empty($function->description)) {
	                $this->errors[] = get_string('function', 'qtype_programmedresp').' '.$function->name.': '.get_string('errorfunctionnodescription', 'qtype_programmedresp');
	                unset($functions[$key]);
	                continue;

	            // Params
	            } else if (empty($function->params)) {
	                $this->errors[] = get_string('function', 'qtype_programmedresp').' '.$function->name.': '.get_string('errorfunctionnoparams', 'qtype_programmedresp');
	                unset($functions[$key]);
	                continue;

	            // Return
	            } else if (empty($function->results) || empty($function->nreturns)) {
	                $this->errors[] = get_string('function', 'qtype_programmedresp').' '.$function->name.': '.get_string('errorfunctionnoresults', 'qtype_programmedresp');
	                unset($functions[$key]);
	                continue;

	            } else if (count($function->results) != intval($function->nreturns)) {
	                $this->errors[] = get_string('function', 'qtype_programmedresp').' '.$function->name.': '.get_string('errorfunctiondifferentnreturns', 'qtype_programmedresp');
	                unset($functions[$key]);
	                continue;

	            } else if (function_exists($function->name)) {
	                $this->errors[] = get_string('function', 'qtype_programmedresp').' '.$function->name.': '.get_string('errorfunctionnative', 'qtype_programmedresp');
	                unset($functions[$key]);
	                continue;
	            } else if (!$this->check_code($function->functioncode)) {
	                $this->errors[] = get_string('function', 'qtype_programmedresp').' '.$function->name.': '.get_string('errorfunctionsuspicious', 'qtype_programmedresp');
	                unset($functions[$key]);
	                continue;

	            }
	        }
	    }

        if (empty($functions)) {
            return true;
        }

        // Checking complete syntax (should be here because of the function_exists() checking
        if (!$this->check_syntax($code)) {
            return false;
        }


        $this->functions = $functions;

        return true;
    }

    /**
     * Checks the PHP code syntax
     * @link http://www.php.net/manual/en/function.eval.php#85790
     * @param $code
     */
    function check_syntax($code) {
        return @eval('return true;' . $code);
    }


    /**
     * Looking for problematic code
     * @todo Look for
     * @param $code
     * @return boolean
     */
    function check_code($code) {

        if (strstr($code, 'exec') != false || strstr($code, 'system') != false) {
            return false;
        }

        return true;
    }


    /**
     * Functions getter
     */
    function get_functions() {
        return $this->functions;
    }


    /**
     * Errors getter
     */
    function get_errors() {
        return $this->errors;
    }

}

?>
