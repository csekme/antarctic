<?php

namespace Framework;

/**
 * Flash notification messages: messages for one-time display using the
 * session storage between requests.
 */
class Flash
{

    /**
     * Succes message type
     * @var string
     */
    const PRIMARY = 'text-bg-primary';

    /**
     * Succes message type
     * @var string
     */
    const SUCCESS = 'text-bg-success';

    /**
     * Information message type
     * @var string
     */
    const INFO = 'text-bg-info';

    /**
     * Warning message type
     * @var string
     */
    const WARNING = 'text-bg-warning';


    /**
     * Warning message type
     * @var string
     */
    const ERROR = 'text-bg-danger';
    

    /**
     * Add a message
     * @param string $message The message content
     * @param string $title The title of message
     * @return void
     */
    public static function addMessage($message, $title = null, $smallTitle = null,  $type = null, $autohide = false)
    {

        //Create array in the session if it doesn't already exist
        if (! isset($_SESSION['flash_notifications'])) {
            $_SESSION['flash_notifications'] = [];
        }

        // Append the message to the array
        $_SESSION['flash_notifications'][] = [
            'message' => $message,
            'title' => $title,
            'smallTitle' => $smallTitle,
            'type'  => $type,
            'autohide' => $autohide
        ];
    }

    /**
     * Get all the messages
     * 
     * @return mixed An array with all the messages or null if none set
     */
    public static function getMessages()
    {
        if (isset($_SESSION['flash_notifications'])) {
            $messages = $_SESSION['flash_notifications'];
            unset($_SESSION['flash_notifications']);
            return $messages;
        }
    }
}
