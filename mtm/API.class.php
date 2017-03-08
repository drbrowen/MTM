<?php
abstract class API
{
    /**
     * Property: method
     * The HTTP method this request was made in, either GET, POST, PUT or DELETE
     */
    protected $method = '';
    /**
     * Property: endpoint
     * The Model requested in the URI. eg: /files
     */
    protected $endpoint = '';
    /**
     * Property: verb
     * An optional additional descriptor about the endpoint, used for things that can
     * not be handled by the basic methods. eg: /files/process
     */
    protected $verb = '';
    /**
     * Property: args
     * Any additional URI components after the endpoint and verb have been removed, in our
     * case, an integer ID for the resource. eg: /<endpoint>/<verb>/<arg0>/<arg1>
     * or /<endpoint>/<arg0>
     */
    protected $args = Array();
    /**
     * Property: file
     * Stores the input of the PUT request
     */
     protected $file = Null;

    /**
     * Constructor: __construct
     * Allow for CORS, assemble and pre-process the data
     */
    public function __construct($request) {
      // Not using cors right now, so, this is commented out.
      //header("Access-Control-Allow-Orgin: *");
      //header("Access-Control-Allow-Methods: *");            
        header("Content-Type: application/json");
        
        session_start();
        if(!isset($_SESSION['user']) || 
        !isset($_SESSION['expiretimestamp']) || 
        $_SESSION['expiretimestamp'] < time() ) {
            session_regenerate_id();
            $data = ['status'=>['error'=>2,'text'=>'Not logged in.']];
            //echo $this->_response($data,307,["Location: /Portal/api/v1/login/"]);
            echo $this->_response($data);
            exit;            
        }

        $_SESSION['timestamp'] = time();
        
        $this->args = explode('/', rtrim($request, '/'));
        $this->endpoint = array_shift($this->args);
        if (array_key_exists(0, $this->args) && !is_numeric($this->args[0])) {
            $this->verb = array_shift($this->args);
        }

        $this->method = $_SERVER['REQUEST_METHOD'];
        if ($this->method == 'POST' && array_key_exists('HTTP_X_HTTP_METHOD', $_SERVER)) {
            if ($_SERVER['HTTP_X_HTTP_METHOD'] == 'DELETE') {
                $this->method = 'DELETE';
            } else if ($_SERVER['HTTP_X_HTTP_METHOD'] == 'PUT') {
                $this->method = 'PUT';
            } else {
                throw new Exception("Unexpected Header");
            }
        }

        switch($this->method) {
        case 'DELETE':
        case 'POST':
            $this->request = $this->_cleanInputs($_GET);
            break;
        case 'GET':
            $this->request = $this->_cleanInputs($_GET);
            break;
        case 'PUT':
            $this->request = $this->_cleanInputs($_GET);
            $this->file = file_get_contents("php://input");
            break;
        default:
            $this->_response('Invalid Method', 405);
            break;
        }
    }

    public function processAPI() {
      if (method_exists($this, $this->endpoint)) {
	return $this->_response($this->{$this->endpoint}($this->args));
      }
      return $this->_response("No Endpoint: $this->endpoint", 404);
    }

    private function _response($data, $status = 200,$headers = []) {
      header("HTTP/1.1 " . $status . " " . $this->_requestStatus($status));
      foreach($headers as $header) {
          header($header);
      }
      return json_encode($data);
    }

    private function _cleanInputs($data) {
      $clean_input = Array();
      if (is_array($data)) {
	foreach ($data as $k => $v) {
	  $clean_input[$k] = $this->_cleanInputs($v);
	}
      } else {
	$clean_input = trim(strip_tags($data));
      }
      return $clean_input;
    }

    private function _requestStatus($code) {
      $status = array(  
		      200 => 'OK',
              307 => 'Resource Temporarily Moved',
              401 => 'Authentication Required',
		      404 => 'Not Found',   
		      405 => 'Method Not Allowed',
		      500 => 'Internal Server Error',
			); 
      return ($status[$code])?$status[$code]:$status[500]; 
    }


}
