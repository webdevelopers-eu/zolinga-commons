<?php

namespace Zolinga\Commons;
use DOMDocument;

// Needed by Email
// We use DOMDocuments in contents and we need to have it serializable

class EmailDocument extends DOMDocument {

    public static function import(DOMDocument $doc): EmailDocument {
        $edoc=new EmailDocument;
        $edoc->appendChild($edoc->importNode($doc->documentElement, true));
        return $edoc;
    }

    /* Method for Serialize interface */
    public function __unserialize (mixed $serialized) {
        $this->loadXML($serialized['xml']);
    }

    /* Method for Serialize interface */
    public function __serialize(): array {
        return ["xml" => $this->saveXML()];
    }
}
