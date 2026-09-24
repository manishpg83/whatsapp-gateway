<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * media_url couldn't be used (not public, too large, wrong file type,
 * download failed, ...). The message is written to be shown to the API
 * caller as-is.
 */
class MediaFetchException extends RuntimeException {}
