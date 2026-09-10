<?php

namespace Jimbojsb\CloudflareAccess;

class CloudflareAccessUserResolver
{
    public function __construct(
        protected string $userModel,
        protected bool $populateGroups,
    ) {}

    /**
     * Find or create the local user record for a verified identity.
     *
     * @param  array<int, string>  $groups
     */
    public function resolve(string $email, string $name, array $groups = []): mixed
    {
        $userModel = $this->userModel;

        $user = $userModel::firstOrNew(['email' => strtolower($email)]);
        $user->name = $name;

        if ($this->populateGroups) {
            $user->groups = $groups;
        } elseif ($user->groups === null) {
            $user->groups = [];
        }

        $user->save();

        return $user;
    }
}
