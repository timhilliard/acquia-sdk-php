<?php

namespace Acquia\Locker\Exception;

/**
 * Lock can not be processes because the uuid is incorrect
 */
class LockUuidMismatch extends LockerException {}
