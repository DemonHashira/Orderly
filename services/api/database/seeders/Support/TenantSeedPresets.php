<?php

namespace Database\Seeders\Support;

use InvalidArgumentException;

final class TenantSeedPresets
{
    public static function all(): array
    {
        return [
            self::otakuStore(),
            self::gearHub(),
        ];
    }

    public static function forSlug(string $slug): array
    {
        foreach (self::all() as $preset) {
            if ($preset['slug'] === $slug) {
                return $preset;
            }
        }

        throw new InvalidArgumentException("Unknown tenant seed preset [{$slug}].");
    }

    private static function otakuStore(): array
    {
        return [
            'slug' => 'otaku-store',
            'name' => 'Otaku Store',
            'customer_seed' => 20260304,
            'customer_domain' => 'example.com',
            'customer_note' => 'Prefers anime releases and manga bundles.',
            'order_reference_prefix' => 'OC',
            'users' => [
                [
                    'first_name' => 'Viktor',
                    'last_name' => 'Logodazhki',
                    'email' => 'vlogodazhki@otakustore.test',
                    'role' => 'Owner',
                ],
                [
                    'first_name' => 'Kiril',
                    'last_name' => 'Hadzhiyski',
                    'email' => 'khadzhiyski@otakustore.test',
                    'role' => 'Order Manager',
                ],
                [
                    'first_name' => 'Nikolay',
                    'last_name' => 'Pugyov',
                    'email' => 'npugyov@otakustore.test',
                    'role' => 'Logistics Manager',
                ],
                [
                    'first_name' => 'Aleksandar',
                    'last_name' => 'Ivanov',
                    'email' => 'aivanov@otakustore.test',
                    'role' => 'Inventory Manager',
                ],
                [
                    'first_name' => 'Petya',
                    'last_name' => 'Queueva',
                    'email' => 'pqueueva@otakustore.test',
                    'role' => null,
                    'permissions' => [
                        'dashboard.view',
                        'inventory.view',
                        'returns.view',
                    ],
                ],
            ],
            'customer_names' => [
                'male_first' => [
                    'Georgi', 'Ivan', 'Radoslav', 'Svetoslav', 'Dimitar', 'Nikolay', 'Viktor', 'Hristo',
                    'Yordan', 'Teodor', 'Borislav', 'Plamen', 'Tsvetan', 'Stanislav', 'Atanas', 'Valentin',
                    'Petar', 'Kiril', 'Lyubomir', 'Milen',
                ],
                'female_first' => [
                    'Mariya', 'Elena', 'Petya', 'Nadezhda', 'Katerina', 'Aleksandra', 'Daniela', 'Desislava',
                    'Kristina', 'Milena', 'Radina', 'Monika', 'Yoana', 'Simona', 'Veronika', 'Kalina',
                    'Gergana', 'Diana', 'Violeta', 'Yana',
                ],
                'male_middle' => [
                    'Petrov', 'Georgiev', 'Ivanov', 'Dimitrov', 'Nikolov', 'Hristov', 'Atanasov', 'Todorov',
                    'Iliev', 'Kostov', 'Mihaylov', 'Radev',
                ],
                'female_middle' => [
                    'Petrova', 'Georgieva', 'Ivanova', 'Dimitrova', 'Nikolova', 'Hristova', 'Atanasova', 'Todorova',
                    'Ilieva', 'Kostova', 'Mihaylova', 'Radeva',
                ],
                'male_last' => [
                    'Stoyanov', 'Georgiev', 'Iliev', 'Atanasov', 'Petkov', 'Angelov', 'Ivanov', 'Nikolov',
                    'Mihaylov', 'Yanev', 'Borisov', 'Rangelov', 'Velikov', 'Kolev', 'Dimitrov', 'Vasilev',
                ],
                'female_last' => [
                    'Petrova', 'Dimitrova', 'Koleva', 'Marinova', 'Ruseva', 'Todorova', 'Hadzhieva', 'Krasteva',
                    'Stefanova', 'Popova', 'Georgieva', 'Ilieva', 'Atanasova', 'Petkova', 'Angelova', 'Vasileva',
                ],
            ],
        ];
    }

    private static function gearHub(): array
    {
        return [
            'slug' => 'gear-hub',
            'name' => 'Gear Hub',
            'customer_seed' => 20260401,
            'customer_domain' => 'gearhub.test',
            'customer_note' => 'Prefers workstation kits and creator bundles.',
            'order_reference_prefix' => 'GH',
            'users' => [
                [
                    'first_name' => 'Elena',
                    'last_name' => 'Petrova',
                    'email' => 'owner@gearhub.test',
                    'role' => 'Owner',
                ],
                [
                    'first_name' => 'Martin',
                    'last_name' => 'Dobrev',
                    'email' => 'orders@gearhub.test',
                    'role' => 'Order Manager',
                ],
                [
                    'first_name' => 'Simona',
                    'last_name' => 'Radeva',
                    'email' => 'logistics@gearhub.test',
                    'role' => 'Logistics Manager',
                ],
                [
                    'first_name' => 'Kaloyan',
                    'last_name' => 'Petrov',
                    'email' => 'inventory@gearhub.test',
                    'role' => 'Inventory Manager',
                ],
                [
                    'first_name' => 'Ina',
                    'last_name' => 'Dispatchova',
                    'email' => 'queue@gearhub.test',
                    'role' => null,
                    'permissions' => [
                        'dashboard.view',
                        'inventory.view',
                        'returns.view',
                    ],
                ],
            ],
            'customer_names' => [
                'male_first' => [
                    'Liam', 'Noah', 'Ethan', 'Lucas', 'Mason', 'Owen', 'Milo', 'Theo', 'Felix', 'Julian',
                    'Adrian', 'Roman', 'Nathan', 'Caleb', 'Leo', 'Alexander', 'Isaac', 'Daniel', 'Arthur', 'Victor',
                ],
                'female_first' => [
                    'Emma', 'Olivia', 'Ava', 'Sofia', 'Mia', 'Nora', 'Elise', 'Clara', 'Aria', 'Lena',
                    'Maya', 'Ivy', 'Eva', 'Naomi', 'Alice', 'Layla', 'Ruby', 'Hazel', 'Julia', 'Amelia',
                ],
                'male_middle' => [
                    'James', 'Oliver', 'Henry', 'Samuel', 'David', 'George', 'Thomas', 'Benjamin', 'Patrick', 'Louis',
                    'Harris', 'Vincent',
                ],
                'female_middle' => [
                    'Rose', 'Grace', 'Marie', 'Claire', 'Anne', 'Louise', 'Jane', 'Hope', 'Skye', 'Kate',
                    'Brooke', 'Faith',
                ],
                'male_last' => [
                    'Bennett', 'Walker', 'Carter', 'Hayes', 'Parker', 'Brooks', 'Coleman', 'Sullivan', 'Miller', 'Porter',
                    'Bailey', 'Reed', 'Murphy', 'Bishop', 'Hunter', 'Cross',
                ],
                'female_last' => [
                    'Parker', 'Sullivan', 'Brooks', 'Harper', 'Miller', 'Bennett', 'Walker', 'Hayes', 'Murphy', 'Turner',
                    'Reed', 'Bishop', 'Coleman', 'Carter', 'Porter', 'Bailey',
                ],
            ],
        ];
    }
}
