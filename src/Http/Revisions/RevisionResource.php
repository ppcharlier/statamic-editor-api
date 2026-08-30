<?php

namespace Ppcharlier\StatamicEditorApi\Http\Revisions;

final class RevisionResource
{
    public static function toArray($revision): array
    {
        $user = $revision->user();

        return [
            'id' => (string) $revision->date()->timestamp,
            'action' => $revision->action(),
            'date' => $revision->date()->toIso8601String(),
            'message' => $revision->message(),
            'user' => $user ? ['id' => $user->id(), 'name' => $user->name(), 'email' => $user->email()] : null,
        ];
    }
}
