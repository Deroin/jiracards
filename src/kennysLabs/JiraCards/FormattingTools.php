<?php
/**
 * Author: nikolai.danylchyk
 * Date: 02.06.2016
 * Time: 12:04
 */

namespace kennysLabs\JiraCards;

class FormattingTools {
    /**
     * put the issues in a format we can work with,
     * so limit to the most used values
     */
    public static function convertJiraIssueToArray($ticket) {

        /**
         * format the time to a readable value
         */

        $time = intval($ticket->fields->timeoriginalestimate);
        if( $time > 0 ) $time = $time / 3600;
        $time = number_format($time, 1)." h";

        /**
         * get avatar from jira
         */
        $avatar = "";
        if( $ticket->fields->assignee ) {
            $av = (array) $ticket->fields->assignee->avatarUrls;
            $avatar = isset($av["48x48"]) ? $av["48x48"] : "";
        }

        /**
         * collect the basic fields from jira
         */
        $collectedTicket = array(
            "priority" => $ticket->fields->priority->name,
            "issuetype" => $ticket->fields->issuetype->name,
            "key" => $ticket->key,
            "summary" => $ticket->fields->summary,
            "reporter" => $ticket->fields->reporter ? $ticket->fields->reporter->displayName : "n/a",
            "assignee" => $ticket->fields->assignee ? $ticket->fields->assignee->displayName : "n/a",
            "parent" => isset($ticket->fields->parent) ? $ticket->fields->parent->key : "",
            "avatar" => $avatar,
            "remaining_time" => $time,
            "updated" =>  substr($ticket->fields->updated,0, 10),
            "story_points" => $ticket->fields->customfield_10133
        );

        /**
         * add custom fields from Jira Agile (epic and rank)
         */
        $customFields = array(
            "epickey" => "customfield_11100",
            "rank" => "customfield_10004"
        );

        foreach( $customFields as $name => $key ) {
            if( property_exists($ticket->fields, $key ) ) {
                $collectedTicket[$name] = $ticket->fields->$key;
            }
        }

        /**
         * return total collection
         */
        return $collectedTicket;
    }

    /**
     * add Agile-epic information to a ticket, since a ticket comes with the
     * link to the epic, but we need to names, which we need to fetch from Jira seperatly
     */
    public static function addEpicNames($tickets, $jira) {

        /**
         * collect all different keys
         */
        $epickeys = array();
        foreach ( $tickets as $ticket ) {
            if(isset($ticket["epickey"]) ) {
                $key = trim($ticket["epickey"]);
                if(!empty($key)) $epickeys[]= $key;
            }
        }
        $epickeys = array_unique($epickeys);
        if ( count($epickeys) == 0 ) return $tickets;


        /**
         * get names pro jira and convert into nicer structure
         */
        $rawEpics = $jira->getIssuesByJql("key IN (".implode(",", $epickeys).")", "key,customfield_11101");
        $epics = array();
        foreach ($rawEpics->issues as $epic) {
            $epics[$epic->key] = $epic->fields->customfield_11101;
        }

        /**
         * modify tickets and add epic names
         */
        for( $i=0; $i < count($tickets); $i++ ) {
            $key = trim($tickets[$i]["epickey"]);
            $tickets[$i]["epic"] = !empty($key) ? $epics[$key] : "";
        }
        return $tickets;
    }
}
