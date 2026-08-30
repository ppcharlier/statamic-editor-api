<?php

namespace Ppcharlier\StatamicEditorApi\Support;

final class MetaFields
{
    /**
     * Blueprint field handles that the API exposes as top-level request/response
     * parameters (e.g. `slug`, `date`) rather than as entries inside `data`.
     *
     * The blueprint "ensures" these fields (see Statamic\Entries\Collection::
     * ensureEntryBlueprintFields()) so they still need to be excluded explicitly:
     * - on write, they are rejected if present inside `data` (client must use the
     *   top-level param instead) and excluded from the field set used to validate
     *   and process `data`, so they never enter entry data;
     * - on read, they are excluded from the `data` object returned to the client,
     *   so GET data is always safe to echo straight back into PATCH data.
     */
    public const HANDLES = ['slug', 'date'];
}
