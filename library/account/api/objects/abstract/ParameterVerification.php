<?php

class ParameterVerification
{
    public const
        TYPE_EMAIL = 0,
        TYPE_PORT = 1, TYPE_IP_ADDRESS = 2,
        TYPE_UUID = 3,
        TYPE_INTEGER = 4, TYPE_DECIMAL = 5, TYPE_NUMERIC = 6,
        TYPE_BOOLEAN = 7,
        TYPE_ALPHA = 8, TYPE_ALPHA_NUMERIC = 9,
        TYPE_PHONE = 10;
    private MethodReply $outcome;

    public function __construct($parameter,
                                $mustBeType = null,
                                $minSize = null, $maxSize = null,
                                $mustContain = null, $mustNotContain = null,
                                $mustStartWith = null, $mustNotStartWith = null,
                                $mustEndWidth = null, $mustNotEndWidth = null)
    {
        if ($mustBeType !== null) {
            if (is_array($mustBeType)) {
                $found = false;

                foreach ($mustBeType as $value) {
                    if ($parameter == $value) {
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    $this->outcome = new MethodReply(
                        false,
                        "Parameter must be one of the following: " . implode(", ", $mustBeType),
                        $parameter
                    );
                    return;
                }
            } else {
                switch ($mustBeType) {
                    case $this::TYPE_EMAIL:
                        if (!is_email($parameter)) {
                            $this->outcome = new MethodReply(false, "Parameter must be an email.", $parameter);
                            return;
                        }
                        break;
                    case $this::TYPE_PHONE:
                        if (!is_phone_number($parameter)) {
                            $this->outcome = new MethodReply(false, "Parameter must be a phone number.", $parameter);
                            return;
                        }
                        break;
                    case $this::TYPE_PORT:
                        if (!is_port($parameter)) {
                            $this->outcome = new MethodReply(false, "Parameter must be a port.", $parameter);
                            return;
                        }
                        break;
                    case $this::TYPE_IP_ADDRESS:
                        if (!is_ip_address($parameter)) {
                            $this->outcome = new MethodReply(false, "Parameter must be an IP address.", $parameter);
                            return;
                        }
                        break;
                    case $this::TYPE_UUID:
                        if (!is_uuid($parameter)) {
                            $this->outcome = new MethodReply(false, "Parameter must be a UUID.", $parameter);
                            return;
                        }
                        break;
                    case $this::TYPE_NUMERIC:
                        if (!is_numeric($parameter)) {
                            $this->outcome = new MethodReply(false, "Parameter must be a number.", $parameter);
                            return;
                        }
                        break;
                    case $this::TYPE_INTEGER:
                        if (!is_int($parameter)) {
                            $this->outcome = new MethodReply(false, "Parameter must be an integer.", $parameter);
                            return;
                        }
                        break;
                    case $this::TYPE_DECIMAL:
                        if (!is_double($parameter) && !is_float($parameter)) {
                            $this->outcome = new MethodReply(false, "Parameter must be a decimal.", $parameter);
                            return;
                        }
                        break;
                    case $this::TYPE_BOOLEAN:
                        if ($parameter != "true" && $parameter != "false") {
                            $this->outcome = new MethodReply(false, "Parameter must be either true or false.", $parameter);
                            return;
                        }
                        break;
                    case $this::TYPE_ALPHA:
                        if (!is_alpha($parameter)) {
                            $this->outcome = new MethodReply(false, "Parameter must be alphabetic.", $parameter);
                            return;
                        }
                        break;
                    case $this::TYPE_ALPHA_NUMERIC:
                        if (!is_alpha_numeric($parameter)) {
                            $this->outcome = new MethodReply(false, "Parameter must be alphanumeric.", $parameter);
                            return;
                        }
                        break;
                    default:
                        break;
                }
            }
        }
        if ($minSize !== null) {
            if (is_numeric($parameter)
                && ($mustBeType === self::TYPE_NUMERIC
                    || $mustBeType === self::TYPE_DECIMAL
                    || $mustBeType === self::TYPE_INTEGER)) {
                if ($parameter < $minSize) {
                    $this->outcome = new MethodReply(false, "Parameter must be at least $minSize.", $parameter);
                    return;
                }
            } else if (strlen($parameter) < $minSize) {
                $this->outcome = new MethodReply(false, "Parameter must be at least $minSize characters long.", $parameter);
                return;
            }
        }
        if ($maxSize !== null) {
            if (is_numeric($parameter)
                && ($mustBeType === self::TYPE_NUMERIC
                    || $mustBeType === self::TYPE_DECIMAL
                    || $mustBeType === self::TYPE_INTEGER)) {
                if ($parameter > $maxSize) {
                    $this->outcome = new MethodReply(false, "Parameter must be at max $minSize.", $parameter);
                    return;
                }
            } else if (strlen($parameter) > $maxSize) {
                $this->outcome = new MethodReply(false, "Parameter must be at max $minSize characters long.", $parameter);
                return;
            }
        }
        if ($mustContain !== null) {
            foreach ($mustContain as $value) {
                if (strpos($parameter, $value) === false) {
                    $this->outcome = new MethodReply(false, "Parameter must contain $value.", $parameter);
                    return;
                }
            }
        }
        if ($mustNotContain !== null) {
            foreach ($mustNotContain as $value) {
                if (strpos($parameter, $value) !== false) {
                    $this->outcome = new MethodReply(false, "Parameter must not contain $value.", $parameter);
                    return;
                }
            }
        }
        if ($mustStartWith !== null && !starts_with($parameter, $mustStartWith)) {
            $this->outcome = new MethodReply(false, "Parameter must start with $mustStartWith.", $parameter);
            return;
        }
        if ($mustNotStartWith !== null && starts_with($parameter, $mustNotStartWith)) {
            $this->outcome = new MethodReply(false, "Parameter must not start with $mustNotStartWith.", $parameter);
            return;
        }
        if ($mustEndWidth !== null && !ends_with($parameter, $mustEndWidth)) {
            $this->outcome = new MethodReply(false, "Parameter must end with $mustEndWidth.", $parameter);
            return;
        }
        if ($mustNotEndWidth !== null && ends_with($parameter, $mustNotEndWidth)) {
            $this->outcome = new MethodReply(false, "Parameter must not end with $mustNotEndWidth.", $parameter);
            return;
        }
        $this->outcome = new MethodReply(true);
    }

    public function getOutcome(): MethodReply
    {
        return $this->outcome;
    }
}
