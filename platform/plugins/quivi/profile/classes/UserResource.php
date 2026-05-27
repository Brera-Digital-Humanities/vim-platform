<?php namespace Quivi\Profile\Classes;

use Winter\User\Models\User;

class UserResource
{
    public static function make(User $user): array
    {
        $groups = $user->groups ? $user->groups->pluck('code')->filter()->values()->all() : [];

        if (isset($user->primary_group) && $user->primary_group && $user->primary_group->code) {
            $groups[] = $user->primary_group->code;
        }

        $data = $user->toArray();
        $data['birth_date'] = $user->birth_date ? $user->birth_date->toDateString() : null;
        $data['groups'] = array_values(array_unique($groups));

        unset(
            $data['password'],
            $data['activation_code'],
            $data['persist_code'],
            $data['reset_password_code']
        );

        return $data;
    }
}
