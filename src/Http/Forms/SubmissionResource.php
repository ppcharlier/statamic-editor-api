<?php

namespace Ppcharlier\StatamicEditorApi\Http\Forms;

final class SubmissionResource
{
    public static function toArray($submission): array
    {
        return [
            'id' => (string) $submission->id(),
            'date' => $submission->date()->toIso8601String(),
            'data' => $submission->data()->all(),
        ];
    }
}
