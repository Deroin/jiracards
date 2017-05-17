<?php

namespace kennysLabs\MainModule\Controllers;

use kennysLabs\CommonLibrary\ApplicationManager\BaseController;
use kennysLabs\JiraCards\JiraApi;
use kennysLabs\JiraCards\FormattingTools;

class IndexController extends BaseController
{
    public function indexAction()
    {
    }

    public function fetchHtmlAction()
    {
        $user = $this->getRequestData('user');
        $password = $this->getRequestData('password');

        $jira = new JiraApi($this->getApplication()->getConfig()->{'jira'}['url']);
        $jira->auth($user, $password);

        if(!empty($this->getRequestData('sprint'))) {
            $jql = sprintf('sprint = %s', $this->getRequestData('sprint'));
        } else if(!empty($this->getRequestData('tickets'))) {
            $tickets = explode(',', $this->getRequestData('tickets'));
            $tickets = implode(' OR id = ', $tickets);
            $jql = sprintf('id = %s', $tickets);
        } else {
            // show error message later...
        }

        $rawTickets = $jira->getIssuesByJql($jql);

        $tickets = array();

        if(!empty($rawTickets->issues)) {
            foreach( $rawTickets->issues as $ticket ) {
                $tickets[] = FormattingTools::convertJiraIssueToArray($ticket);
            }
        }

        $this->setViewVar('tickets', $tickets);
    }

}
