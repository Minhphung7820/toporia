<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Infrastructure\Persistence\Models\{
    CountryModel,
    CityModel,
    AuthorModel,
    BookModel,
    ChapterModel,
    PageModel,
    PublisherModel,
    RoleModel,
    PermissionModel,
    UserModel,
    CategoryModel
};
use Toporia\Framework\Database\Seeder;
use Faker\Factory as FakerFactory;

/**
 * Relationship Test Seeder
 *
 * Seeds data for comprehensive relationship testing:
 * - Countries, Cities, Authors, Books, Chapters, Pages
 * - Publishers, Roles, Permissions
 * - All pivot tables
 *
 * Usage:
 *   php console db:seed --class=RelationshipTestSeeder
 *   php console db:seed --class=RelationshipTestSeeder --countries=100 --authors=500
 */
final class RelationshipTestSeeder extends Seeder
{
    private const DEFAULTS = [
        'countries' => 20,
        'cities_per_country' => 10,
        'authors_per_city' => 5,
        'books_per_author' => 3,
        'chapters_per_book' => 10,
        'pages_per_chapter' => 15,
        'publishers' => 15,
        'roles' => 10,
        'permissions' => 50,
        'categories' => 20,
    ];

    protected int $batchSize = 500;
    private array $stats = [];
    private array $ids = [];
    private $faker;

    public function __construct()
    {
        parent::__construct();
        $this->faker = FakerFactory::create();
    }

    /**
     * {@inheritdoc}
     */
    public function dependencies(): array
    {
        return [UserSeeder::class];
    }

    /**
     * {@inheritdoc}
     */
    protected function seed(): void
    {
        $this->info('🌱 Starting Relationship Test Seeder...');
        $this->newLine();

        try {
            // Seed in dependency order
            $this->seedCountries();
            $this->seedCities();
            $this->seedPublishers();
            $this->seedAuthors();
            $this->seedBooks();
            $this->seedChapters();
            $this->seedPages();
            $this->seedRoles();
            $this->seedPermissions();
            $this->seedCategories();

            // Seed pivot tables
            $this->seedRoleUser();
            $this->seedPermissionRole();
            $this->seedBookCategory();

            $this->logCompletion();
        } catch (\Throwable $e) {
            $this->error("❌ Seeding failed: {$e->getMessage()}");
            $this->line("   File: {$e->getFile()}:{$e->getLine()}");
            throw $e;
        }
    }

    private function seedCountries(): void
    {
        $count = $this->getOption('countries') ?? self::DEFAULTS['countries'];
        $this->line("  Creating {$count} Countries...");

        $continents = ['Asia', 'Europe', 'North America', 'South America', 'Africa', 'Oceania'];
        $data = [];
        $now = now()->toDateTimeString();

        for ($i = 0; $i < $count; $i++) {
            $data[] = [
                'name' => $this->faker->unique()->country(),
                'code' => strtoupper($this->faker->unique()->lexify('??')),
                'continent' => $this->faker->randomElement($continents),
                'population' => $this->faker->numberBetween(1000000, 1000000000),
                'is_active' => $this->faker->boolean(90),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insert('countries', $data);
        $this->ids['countries'] = $this->getIds('countries', $count);
        $this->stats['countries'] = $count;
        $this->line("  ✓ Created {$count} Countries");
    }

    private function seedCities(): void
    {
        $perCountry = $this->getOption('cities_per_country') ?? self::DEFAULTS['cities_per_country'];
        $this->line("  Creating Cities ({$perCountry} per country)...");

        $data = [];
        $now = now()->toDateTimeString();
        $total = 0;

        foreach ($this->ids['countries'] as $countryId) {
            $cityCount = $this->faker->numberBetween((int)($perCountry * 0.5), $perCountry);
            $hasCapital = false;

            for ($i = 0; $i < $cityCount; $i++) {
                $isCapital = !$hasCapital && $i === 0;
                if ($isCapital) $hasCapital = true;

                $data[] = [
                    'country_id' => $countryId,
                    'name' => $this->faker->city(),
                    'population' => $this->faker->numberBetween(100000, 20000000),
                    'is_capital' => $isCapital,
                    'latitude' => $this->faker->latitude(),
                    'longitude' => $this->faker->longitude(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $total++;

                // Batch insert for performance
                if (count($data) >= $this->batchSize) {
                    $this->insert('cities', $data);
                    $data = [];
                }
            }
        }

        if (!empty($data)) {
            $this->insert('cities', $data);
        }

        $this->ids['cities'] = $this->getIds('cities', $total);
        $this->stats['cities'] = $total;
        $this->line("  ✓ Created {$total} Cities");
    }

    private function seedPublishers(): void
    {
        $count = $this->getOption('publishers') ?? self::DEFAULTS['publishers'];
        $this->line("  Creating {$count} Publishers...");

        $data = [];
        $now = now()->toDateTimeString();

        for ($i = 0; $i < $count; $i++) {
            $data[] = [
                'country_id' => $this->faker->randomElement($this->ids['countries']),
                'name' => $this->faker->company() . ' Publishing',
                'email' => $this->faker->companyEmail(),
                'website' => $this->faker->url(),
                'founded_year' => $this->faker->numberBetween(1900, 2024),
                'is_active' => $this->faker->boolean(85),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insert('publishers', $data);
        $this->ids['publishers'] = $this->getIds('publishers', $count);
        $this->stats['publishers'] = $count;
        $this->line("  ✓ Created {$count} Publishers");
    }

    private function seedAuthors(): void
    {
        $perCity = $this->getOption('authors_per_city') ?? self::DEFAULTS['authors_per_city'];
        $this->line("  Creating Authors ({$perCity} per city)...");

        // Get user IDs if available
        $userIds = [];
        $connection = $this->getConnection();
        $users = $connection->select('SELECT id FROM users LIMIT 100');
        if (!empty($users)) {
            $userIds = array_column($users, 'id');
        }

        $data = [];
        $now = now()->toDateTimeString();
        $total = 0;

        foreach ($this->ids['cities'] as $cityId) {
            $authorCount = $this->faker->numberBetween(1, $perCity);

            for ($i = 0; $i < $authorCount; $i++) {
                $data[] = [
                    'user_id' => !empty($userIds) && $this->faker->boolean(30) ? $this->faker->randomElement($userIds) : null,
                    'city_id' => $cityId,
                    'pen_name' => $this->faker->name(),
                    'bio' => $this->faker->sentence(20),
                    'books_count' => 0, // Will update later
                    'rating' => $this->faker->randomFloat(2, 3.0, 5.0),
                    'is_verified' => $this->faker->boolean(70),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $total++;

                if (count($data) >= $this->batchSize) {
                    $this->insert('authors', $data);
                    $data = [];
                }
            }
        }

        if (!empty($data)) {
            $this->insert('authors', $data);
        }

        $this->ids['authors'] = $this->getIds('authors', $total);
        $this->stats['authors'] = $total;
        $this->line("  ✓ Created {$total} Authors");
    }

    private function seedBooks(): void
    {
        $perAuthor = $this->getOption('books_per_author') ?? self::DEFAULTS['books_per_author'];
        $this->line("  Creating Books ({$perAuthor} per author)...");

        $data = [];
        $now = now()->toDateTimeString();
        $total = 0;

        foreach ($this->ids['authors'] as $authorId) {
            $bookCount = $this->faker->numberBetween(1, $perAuthor);

            for ($i = 0; $i < $bookCount; $i++) {
                $pagesCount = $this->faker->numberBetween(150, 800);
                $reviewsCount = $this->faker->numberBetween(0, 500);
                $rating = $reviewsCount > 0 ? $this->faker->randomFloat(2, 2.5, 5.0) : 0;

                $data[] = [
                    'author_id' => $authorId,
                    'publisher_id' => $this->faker->boolean(80) ? $this->faker->randomElement($this->ids['publishers']) : null,
                    'title' => $this->faker->sentence(rand(3, 8)),
                    'isbn' => $this->faker->isbn13(),
                    'description' => $this->faker->paragraph(5),
                    'pages_count' => $pagesCount,
                    'price' => $this->faker->randomFloat(2, 9.99, 99.99),
                    'published_year' => $this->faker->numberBetween(1950, 2024),
                    'stock' => $this->faker->numberBetween(0, 1000),
                    'rating' => $rating,
                    'reviews_count' => $reviewsCount,
                    'is_bestseller' => $rating >= 4.5 && $reviewsCount > 100 ? $this->faker->boolean(30) : false,
                    'is_available' => $this->faker->boolean(90),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $total++;

                if (count($data) >= $this->batchSize) {
                    $this->insert('books', $data);
                    $data = [];
                }
            }
        }

        if (!empty($data)) {
            $this->insert('books', $data);
        }

        // Update authors books_count
        $connection = $this->getConnection();
        $connection->execute('UPDATE authors SET books_count = (SELECT COUNT(*) FROM books WHERE books.author_id = authors.id)');

        $this->ids['books'] = $this->getIds('books', $total);
        $this->stats['books'] = $total;
        $this->line("  ✓ Created {$total} Books");
    }

    private function seedChapters(): void
    {
        $perBook = $this->getOption('chapters_per_book') ?? self::DEFAULTS['chapters_per_book'];
        $this->line("  Creating Chapters ({$perBook} per book)...");

        $data = [];
        $now = now()->toDateTimeString();
        $total = 0;

        foreach ($this->ids['books'] as $bookId) {
            $chapterCount = $this->faker->numberBetween((int)($perBook * 0.5), $perBook);

            for ($i = 1; $i <= $chapterCount; $i++) {
                $wordsCount = $this->faker->numberBetween(500, 5000);
                $data[] = [
                    'book_id' => $bookId,
                    'chapter_number' => $i,
                    'title' => 'Chapter ' . $i . ': ' . $this->faker->sentence(4),
                    'summary' => $this->faker->paragraph(3),
                    'pages_count' => $this->faker->numberBetween(10, 50),
                    'words_count' => $wordsCount,
                    'reading_time_minutes' => (int)($wordsCount / 200),
                    'is_free_preview' => $i <= 2,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $total++;

                if (count($data) >= $this->batchSize) {
                    $this->insert('chapters', $data);
                    $data = [];
                }
            }
        }

        if (!empty($data)) {
            $this->insert('chapters', $data);
        }

        $this->ids['chapters'] = $this->getIds('chapters', $total);
        $this->stats['chapters'] = $total;
        $this->line("  ✓ Created {$total} Chapters");
    }

    private function seedPages(): void
    {
        $perChapter = $this->getOption('pages_per_chapter') ?? self::DEFAULTS['pages_per_chapter'];
        $this->line("  Creating Pages ({$perChapter} per chapter)...");

        $data = [];
        $now = now()->toDateTimeString();
        $total = 0;

        // Process in chunks to avoid memory issues
        $chapterChunks = array_chunk($this->ids['chapters'], 100);

        foreach ($chapterChunks as $chapterIds) {
            foreach ($chapterIds as $chapterId) {
                $pageCount = $this->faker->numberBetween((int)($perChapter * 0.5), $perChapter);

                for ($i = 1; $i <= $pageCount; $i++) {
                    $wordsCount = $this->faker->numberBetween(200, 800);
                    $data[] = [
                        'chapter_id' => $chapterId,
                        'page_number' => $i,
                        'content' => $this->faker->paragraphs(5, true),
                        'words_count' => $wordsCount,
                        'has_images' => $this->faker->boolean(40),
                        'metadata' => json_encode([
                            'footnotes' => $this->faker->numberBetween(0, 5),
                            'illustrations' => $this->faker->boolean(20),
                        ]),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $total++;

                    if (count($data) >= $this->batchSize) {
                        $this->insert('pages', $data);
                        $data = [];
                    }
                }
            }
        }

        if (!empty($data)) {
            $this->insert('pages', $data);
        }

        $this->stats['pages'] = $total;
        $this->line("  ✓ Created {$total} Pages");
    }

    private function seedRoles(): void
    {
        $count = $this->getOption('roles') ?? self::DEFAULTS['roles'];
        $this->line("  Creating {$count} Roles...");

        $data = [];
        $now = now()->toDateTimeString();

        $roleNames = ['admin', 'editor', 'author', 'reviewer', 'moderator', 'subscriber', 'contributor', 'manager', 'developer', 'analyst'];

        for ($i = 0; $i < min($count, count($roleNames)); $i++) {
            $data[] = [
                'name' => $roleNames[$i],
                'display_name' => ucfirst($roleNames[$i]),
                'description' => $this->faker->sentence(10),
                'level' => ($i + 1) * 5,
                'is_active' => $this->faker->boolean(90),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insert('roles', $data);
        $this->ids['roles'] = $this->getIds('roles', count($data));
        $this->stats['roles'] = count($data);
        $this->line("  ✓ Created " . count($data) . " Roles");
    }

    private function seedPermissions(): void
    {
        $count = $this->getOption('permissions') ?? self::DEFAULTS['permissions'];
        $this->line("  Creating {$count} Permissions...");

        $data = [];
        $now = now()->toDateTimeString();

        $resources = ['book', 'author', 'category', 'user', 'role', 'permission'];
        $actions = ['create', 'read', 'update', 'delete', 'publish', 'manage'];

        $permCount = 0;
        foreach ($resources as $resource) {
            foreach ($actions as $action) {
                if ($permCount >= $count) break 2;

                $data[] = [
                    'name' => "{$resource}.{$action}",
                    'display_name' => ucfirst($action) . ' ' . ucfirst($resource),
                    'description' => "Permission to {$action} {$resource}",
                    'resource' => $resource,
                    'action' => $action,
                    'is_active' => $this->faker->boolean(95),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $permCount++;
            }
        }

        $this->insert('permissions', $data);
        $this->ids['permissions'] = $this->getIds('permissions', count($data));
        $this->stats['permissions'] = count($data);
        $this->line("  ✓ Created " . count($data) . " Permissions");
    }

    private function seedCategories(): void
    {
        $count = $this->getOption('categories') ?? self::DEFAULTS['categories'];
        $this->line("  Creating {$count} Categories...");

        // Check if categories already exist
        $existingCount = (int)$this->getConnection()->selectOne('SELECT COUNT(*) as count FROM categories')['count'];
        if ($existingCount > 0) {
            $this->ids['categories'] = $this->getIds('categories', $existingCount);
            $this->line("  ℹ Using {$existingCount} existing Categories");
            return;
        }

        $data = [];
        $now = now()->toDateTimeString();

        $genreNames = [
            'Fiction',
            'Non-Fiction',
            'Mystery',
            'Thriller',
            'Romance',
            'Science Fiction',
            'Fantasy',
            'Horror',
            'Biography',
            'History',
            'Self-Help',
            'Cooking',
            'Travel',
            'Art',
            'Poetry',
            'Drama',
            'Comedy',
            'Adventure',
            'Crime',
            'Philosophy'
        ];

        for ($i = 0; $i < min($count, count($genreNames)); $i++) {
            $name = $genreNames[$i];
            $data[] = [
                'name' => $name,
                'slug' => strtolower(str_replace(' ', '-', $name)),
                'description' => $this->faker->sentence(15),
                'is_active' => $this->faker->boolean(95),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->insert('categories', $data);
        $this->ids['categories'] = $this->getIds('categories', count($data));
        $this->stats['categories'] = count($data);
        $this->line("  ✓ Created " . count($data) . " Categories");
    }

    private function seedRoleUser(): void
    {
        $this->line("  Creating Role-User Relationships...");

        // Get users
        $users = $this->getConnection()->select('SELECT id FROM users LIMIT 100');
        if (empty($users)) {
            $this->line("  ℹ Skipped (no users found)");
            return;
        }

        $userIds = array_column($users, 'id');
        $data = [];
        $now = now()->toDateTimeString();
        $total = 0;

        foreach ($userIds as $userId) {
            $roleCount = $this->faker->numberBetween(1, 3);
            $selectedRoles = $this->faker->randomElements($this->ids['roles'], min($roleCount, count($this->ids['roles'])));

            foreach ($selectedRoles as $roleId) {
                $data[] = [
                    'role_id' => $roleId,
                    'user_id' => $userId,
                    'assigned_at' => $now,
                    'assigned_by' => $this->faker->boolean(50) ? $this->faker->randomElement($userIds) : null,
                    'expires_at' => $this->faker->boolean(20) ? $this->faker->dateTimeBetween('+1 month', '+1 year')->format('Y-m-d H:i:s') : null,
                    'is_active' => $this->faker->boolean(90),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $total++;
            }
        }

        $this->insert('role_user', $data);
        $this->stats['role_user'] = $total;
        $this->line("  ✓ Created {$total} Role-User Relationships");
    }

    private function seedPermissionRole(): void
    {
        $this->line("  Creating Permission-Role Relationships...");

        $data = [];
        $now = now()->toDateTimeString();
        $total = 0;

        foreach ($this->ids['roles'] as $roleId) {
            $permCount = $this->faker->numberBetween(5, 20);
            $selectedPerms = $this->faker->randomElements($this->ids['permissions'], min($permCount, count($this->ids['permissions'])));

            foreach ($selectedPerms as $permId) {
                $data[] = [
                    'permission_id' => $permId,
                    'role_id' => $roleId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $total++;
            }
        }

        $this->insert('permission_role', $data);
        $this->stats['permission_role'] = $total;
        $this->line("  ✓ Created {$total} Permission-Role Relationships");
    }

    private function seedBookCategory(): void
    {
        $this->line("  Creating Book-Category Relationships...");

        $data = [];
        $now = now()->toDateTimeString();
        $total = 0;

        foreach ($this->ids['books'] as $index => $bookId) {
            $catCount = $this->faker->numberBetween(1, 4);
            $selectedCats = $this->faker->randomElements($this->ids['categories'], min($catCount, count($this->ids['categories'])));

            foreach ($selectedCats as $catIndex => $catId) {
                $data[] = [
                    'book_id' => $bookId,
                    'category_id' => $catId,
                    'is_primary' => $catIndex === 0,
                    'order' => $catIndex + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $total++;

                if (count($data) >= $this->batchSize) {
                    $this->insert('book_category', $data);
                    $data = [];
                }
            }
        }

        if (!empty($data)) {
            $this->insert('book_category', $data);
        }

        $this->stats['book_category'] = $total;
        $this->line("  ✓ Created {$total} Book-Category Relationships");
    }

    private function getIds(string $table, int $count): array
    {
        $connection = $this->getConnection();
        $maxId = (int)$connection->selectOne("SELECT MAX(id) as max_id FROM {$table}")['max_id'];
        return range($maxId - $count + 1, $maxId);
    }

    private function logCompletion(): void
    {
        $this->newLine();
        $this->info('🎉 Seeding completed successfully!');
        $this->newLine();
        $this->info('📊 Statistics:');

        foreach ($this->stats as $entity => $count) {
            $entityName = ucwords(str_replace('_', ' ', $entity));
            $this->line("   - {$entityName}: " . number_format($count));
        }

        $this->newLine();
        $totalRecords = array_sum($this->stats);
        $this->info("   Total Records: " . number_format($totalRecords));
    }
}
