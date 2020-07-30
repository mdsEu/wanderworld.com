<?php

if (!function_exists('sendJson')) {
    /**
     * @param $value mixed
     * @param $message string | array
     * @return ResponseJson
     */
    function sendResponse($value,$messages = '', $success = true)
    {
        if (is_string($messages) && !empty($messages)) {
            $messages = [$messages];
        } elseif (is_string($messages)) {
            $messages = [];
        }
        return response()->json(array(
                'success' => $success,
                'data' => $value,
                'messages' => $messages,
            )
        );
    }
}
