<?php

class GameCloudConnection
{
    private object|bool|null $properties;

    public function __construct(int|string $reason, string $key = "name")
    {
        $query = get_sql_query(
            GameCloudVariables::ACCEPTED_PURPOSES_TABLE,
            null,
            array(
                array($key, $reason),
            )
        );
        if (empty($query)) {
            $this->properties = false;
            return; // Distribute exception because this connection is using unknown content
        }
        $purpose = $query[0];

        if ($purpose->deletion_date !== null) {
            $this->properties = null;
            return; // Do not print exception as this connection is just using outdated content
        }
        $replacedBy = $purpose->replaced_by;

        if ($replacedBy !== null) {
            $new = new GameCloudConnection($replacedBy, "id");
            $this->properties = $new->getProperties();
            return;
        }
        $allowedProducts = $purpose->allowed_products;

        if ($allowedProducts !== null) {
            $purpose->allowed_products = explode("|", $allowedProducts);
        }
        $this->properties = $purpose;
    }

    public function getProperties()
    {
        return $this->properties;
    }
}
