<?php

function _handleError($code, $description, $file = null, $line = null, $context = null)
{
    $data = array(
        'code' => $code,
        'description' => $description,
        'file' => $file,
        'line' => $line,
        'context' => $context,
        'path' => $file,
    );
    _logError($data);
}


function _logError($data = [])
{
    $data_a = $data;
    $data = print_r($data, true);

    $r = ["error" => "Logic Exception"];

    if (debug) $r["debug"] = $data;

    echo json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    die();
}

/**
 * Fatal Error Shutdown Handler
 *
 * Extreme output and terminate
 *
 * @return void
 */
function _fatalErrorShutdownHandler()
{
    $r = error_get_last();
    if ($r['type'] === E_ERROR) {
        _handleError($r['type'], $r['message'], $r['file'], $r['line']);
    }
}

/**
 * Global Exception Handler
 *
 * Writes logs and terminates, does all apiException's work
 *
 * @param Exception $e Unhandled exception
 * @return void
 */
function _handleException($e)
{
    try {
        if ($e instanceof PDOException) {
            $data = [
                "error" => $e->getMessage(),
                "line" => $e->getLine(),
                "file" => $e->getFile(),
                "trace" => $e->getTraceAsString(),
            ];
                _logError($data);

        } else {
            $r = __ExceptionToArray($e);
            _handleError($r[0], $r[1], $r[2], $r[3], $r[4]);
        }
    } catch (Error $m) {
        $ty = ["error" => "Core failed to display error message"];
        if (debug) {
            $ty["debug"] = __ExceptionToArray($m);
        }

        die(json_encode($ty));
    } finally {
        die('{"error": "Unhandled core-level error"}');
    }
}


function __ExceptionToArray($e)
{
    $code = $e->getCode();
    $description = $e->getMessage();
    $file = $e->getFile();
    $line = $e->getLine();
    $context = $e->getTraceAsString();

    return [$code, $description, $file, $line, $context];
}

set_exception_handler("_handleException");
register_shutdown_function('_fatalErrorShutdownHandler');
