<?php
/**
 * Author: nikolai.danylchyk
 * Date: 02.06.2016
 * Time: 12:04
 */

namespace kennysLabs\JiraCards;

class JiraApi {

    /**
     * rest request vars
     */
    const REQUEST_GET    = "GET";
    const REQUEST_POST   = "POST";
    const REQUEST_PUT    = "PUT";
    const REQUEST_DELETE = "DELETE";

    /**
     * @var string path to JIRA
     */
    protected $path = "";

    /**
     * @var string JIRA username
     */
    protected $username = "";

    /**
     * @var string JIRA password
     */
    protected $password = "";

    /**
     * ini new object with the JIRA path passed
     * @param $path
     */
    public function __construct($path) {
        $this->path = $path;
    }

    /**
     * set the username and password for the current request
     * this could change later to a token authentication
     * @param $username
     * @param $password
     */
    public function auth($username, $password) {
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * Gets base information about a ticket
     * @param $ticket
     * @return array
     */
    public function baseInformationForIssue($ticket) {
        return [];

        // TODO: properly debug ticket info.. some fields are missing still

        $name = $ticket->fields->summary;
        $reporter = $ticket->fields->reporter->name;
        $assignee = $ticket->fields->assignee->name;
        $projectName = $ticket->fields->project->name;
        $projectKey = $ticket->fields->project->key;
        $created = strtotime($ticket->fields->created);
        $priority = $ticket->fields->priority->name;
        $status = $ticket->fields->status->name;
        $dueDate = strtotime($ticket->fields->duedate);

        $fixVersionsRaw = $ticket->fields->fixVersions;
        $fixVersions = array();
        if( is_array($fixVersionsRaw) ) {
            foreach( $fixVersionsRaw as $fv ) {
                $fixVersions[]= $fv->name;
            }
        }

        // collect ticket information
        return array(
            "name" => $name,
            "fixVersions" => $fixVersions,
            "status" => $status,
            "reporter" => $reporter,
            "created" => $created,
            "daysSinceCreation" => self::getDateDifference($created),
            "priority" => $priority,
            "assignee" => $assignee,
            "key" => $ticket->key,
            "projectKey" => $projectKey,
            "projectName" => $projectName,
            "dueDate" => $dueDate,
            "daysUntilDueDate" => self::getDateDifference($dueDate,false),
        );
    }

    /**
     * return a list of issues, based on a JQL search string
     * @param $jql
     * @param $fields
     * @return mixed
     */
    public function getIssuesByJql($jql, $fields = "*all") {

        // encode jql string, but decode slashes (/),
        // JIRA can only handle them decoded
        return $this->query(static::REQUEST_GET, "search?fields=".$fields."&maxResults=200&jql=".
            str_replace("%252F", "/",
                rawurlencode($jql)
            )
        );
    }

    /**
     * get all available versions of a project
     * jira does not provide any limit or order features, yet
     * @param string $projectkey
     * @return mixed
     */
    public function getVersionsByProject($projectkey) {
        return $this->query(static::REQUEST_GET, "project/".$projectkey."/versions?");
    }

    /**
     * @param $ticketKey
     * @param $newData
     * @param bool $transition
     * @return mixed
     */
    public function updateTicket($ticketKey, $newData, $transition = false) {
        $method = static::REQUEST_PUT;
        $transitionUrl = "";
        if($transition==true){
            $transitionUrl = '/transitions?expand=transitions.fields';
            $method = static::REQUEST_POST;
        }
        return $this->query($method, "issue/".$ticketKey.$transitionUrl, $newData);
    }

    /**
     * query the API with adding username and password
     * tries to convert json result to objects
     * @param string $method
     * @param string $query
     * @param array $data
     * @return mixed
     * @throws Exception
     */
    protected function query($method, $query, $data = array()) {
        $result = $this->sendRequest( $method, $query, $data );
        if( $result === false ) throw new Exception("It wasn't possible to get JIRA url ".$this->path . $query." with username " . $this->username);
        return json_decode($result);
    }

    /**
     * send the actual JIRA request by using the passed REST/HTTP method
     * @param $method
     * @param $query
     * @param array $data
     * @return string
     * @throws Exception
     */
    public function sendRequest($method, $query, $data = array()) {

        /**
         * start to build the header
         */
        $header = array();
        $header[] = "Content-Type: application/json";

        if (!empty($data)) {
            $query .= "?" . http_build_query($data);
        }

        $jdata = json_encode($data);
        $ch = curl_init();
        //configure CURL
        curl_setopt_array($ch, array(
            CURLOPT_URL => $this->path . $query,
            CURLOPT_USERPWD => $this->username . ':' . $this->password,
            // CURLOPT_POSTFIELDS => $jdata,
            CURLOPT_HTTPHEADER => array('Content-type: application/json'),
            CURLOPT_RETURNTRANSFER => true
        ));
        $result = curl_exec($ch);

        if (is_null($result)) {
            throw new Exception("JIRA Rest server returns unexpected result.");
        }
        return $result;
    }

    /**
     * get date difference in days
     * @param int $date
     * @param bool $past
     * @return int
     */
    public function getDateDifference($date, $past = true) {
        $difference = floor(((time() - $date) / 60 / 60 / 24)) * $mult = $past!==true? -1:1;
        return $difference;
    }

    /**
     * @param string $url
     * @return string
     */
    private function get_http_response_code($url)
    {
        $headers = get_headers($url);
        return substr($headers[0], 9, 3);
    }
}
