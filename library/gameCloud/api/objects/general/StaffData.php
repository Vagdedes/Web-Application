<?php

class StaffData
{

    private array $data;

    public function __construct(string|array $provider)
    {
        $data = array();

        if (is_array($provider)) {
            foreach ($provider as $string) {
                $newData = new StaffData($string);

                if ($newData->found()) {
                    foreach ($newData->getArray() as $key => $value) {
                        $data[$key] = $value;
                    }
                }
            }
        } else {
            foreach (explode(" ", str_replace(",", "", str_replace("(", "", str_replace(")", "", $provider))), GameCloudVerification::monthly_staff_limit) as $word) {
                if (!empty($word)) {
                    $split = explode("|", $word, 4);

                    if (sizeof($split) === 3) {
                        $uuid = $split[1];
                        $uuidReplaced = str_replace("-", "", $uuid);

                        if (isset($data[$uuidReplaced])) {
                            foreach (array(0, 1) as $position) {
                                $value = $data[$uuidReplaced][$position];

                                if (empty($value)) {
                                    $data[$uuidReplaced][$position] = $value;
                                }
                            }
                        } else if (is_uuid($uuid)) {
                            $data[$uuidReplaced] = array($split[0], $split[2]);
                        }
                    }
                }
            }
        }
        $this->data = $data;
    }

    function found(): bool
    {
        return !empty($this->data);
    }

    function getArray(): array
    {
        return $this->data;
    }
}
