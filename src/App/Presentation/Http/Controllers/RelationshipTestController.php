<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers;

use App\Infrastructure\Persistence\Models\{
    CountryModel,
    CityModel,
    AuthorModel,
    BookModel,
    ChapterModel,
    PublisherModel,
    RoleModel,
    PermissionModel,
    UserModel,
    CategoryModel
};
use Toporia\Framework\Http\Request;
use Toporia\Framework\Database\Query\QueryBuilder as QB;

/**
 * Relationship Test Controller
 *
 * Comprehensive test suite for ALL Eloquent relationship types:
 * - hasOne
 * - hasMany
 * - belongsTo
 * - belongsToMany
 * - hasOneThrough
 * - hasManyThrough
 * - Polymorphic relationships
 * - Nested eager loading
 * - Complex constraints
 */
final class RelationshipTestController extends BaseController
{
    /**
     * Test ALL relationship types with ultra complex queries
     *
     * GET /api/relationships/test-all
     */
    public function testAllRelationships(Request $request)
    {
        QB::enableQueryLog();

        $results = [];

        // ================================================================================
        // TEST 1: hasOne Relationship
        // ================================================================================
        // City -> topAuthor (hasOne)
        // $results['test_1_has_one'] = [
        //     'title' => 'hasOne: City with Top Author',
        //     'description' => 'Get cities with their top-rated verified author',
        //     'data' => CityModel::with('topAuthor')
        //         ->whereHas('topAuthor')
        //         ->orderBy('population', 'DESC')
        //         ->limit(5)
        //         ->get()
        //         ->toArray()
        // ];

        // ================================================================================
        // TEST 2: hasMany Relationship
        // ================================================================================
        // Country -> cities (hasMany)
        $results['test_2_has_many'] = [
            'title' => 'hasMany: Countries with Cities',
            'description' => 'Countries with their cities, ordered by population',
            'data' => CountryModel::with(['cities' => function ($q) {
                $q->where('population', '>', 5000000)
                    ->orderBy('population', 'DESC')
                    ->limit(3);
            }])
                ->whereHas('cities', function ($q) {
                    $q->where('population', '>', 10000000);
                })
                ->withCount('cities')
                ->having('cities_count', '>', 0)
                ->orderBy('cities_count', 'DESC')
                ->limit(5)
                ->get()
                ->toArray()
        ];

        // ================================================================================
        // TEST 3: belongsTo Relationship
        // ================================================================================
        // Author -> city, city -> country (nested belongsTo)
        // $results['test_3_belongs_to'] = [
        //     'title' => 'belongsTo: Authors with City and Country',
        //     'description' => 'Nested belongsTo: Author -> City -> Country',
        //     'data' => AuthorModel::with(['city.country', 'user'])
        //         ->where('is_verified', true)
        //         ->orderBy('rating', 'DESC')
        //         ->limit(10)
        //         ->get()
        //         ->toArray()
        // ];

        // ================================================================================
        // TEST 4: belongsToMany Relationship
        // ================================================================================
        // Book -> categories (belongsToMany with pivot)
        $results['test_4_belongs_to_many'] = [
            'title' => 'belongsToMany: Books with Categories',
            'description' => 'Books with their categories and pivot data',
            'data' => BookModel::with(['categories' => function ($q) {
                $q->wherePivot('is_primary', 1)
                    ->limit(2);
            }, 'author', 'publisher'])
                // ->whereHas('categories', function ($q) {
                //     $q->where('name', 'LIKE', '%Fiction%');
                // })
                ->where('is_available', true)
                ->orderBy('rating', 'DESC')
                ->limit(10)
                ->get()
                ->toArray()
        ];

        // // ================================================================================
        // // TEST 5: hasOneThrough Relationship
        // // ================================================================================
        // // Author -> country through city (hasOneThrough)
        // $results['test_5_nested_belongs_to'] = [
        //     'title' => 'Nested belongsTo: Authors with Country',
        //     'description' => 'Author -> City -> Country using nested belongsTo',
        //     'data' => AuthorModel::with(['city.country' => function ($q) {
        //         $q->select('id', 'name', 'code');
        //     }])
        //         ->whereHas('city.country', function ($q) {
        //             $q->where('continent', 'Asia');
        //         })
        //         ->where('is_verified', true)
        //         ->orderBy('rating', 'DESC')
        //         ->limit(10)
        //         ->get()
        //         ->toArray()
        // ];

        // // ================================================================================
        // // TEST 6: hasManyThrough Relationship (Simple)
        // // ================================================================================
        // // City -> books through authors (hasManyThrough)
        $results['test_6_has_many_through_simple'] = [
            'title' => 'hasManyThrough: City Books through Authors',
            'description' => 'City -> Authors -> Books using hasManyThrough',
            'data' => CityModel::with(['books' => function ($q) {
                $q->where('is_available', true)
                    ->orderBy('rating', 'DESC')
                    ->limit(5);
            }, 'country'])
                ->whereHas('books', function ($q) {
                    $q->where('rating', '>=', 4.0);
                })
                ->withCount('books')
                ->having('books_count', '>=', 3)
                ->orderBy('books_count', 'DESC')
                ->limit(10)
                ->get()
                ->toArray()
        ];

        // // ================================================================================
        // // TEST 7: hasManyThrough Relationship (Complex)
        // // ================================================================================
        // // Country -> authors through cities (hasManyThrough)
        // $results['test_7_has_many_through_complex'] = [
        //     'title' => 'hasManyThrough: Country Authors through Cities',
        //     'description' => 'Country -> Cities -> Authors with complex constraints',
        //     'data' => CountryModel::with(['authors' => function ($q) {
        //         $q->where('is_verified', true)
        //             ->where('rating', '>=', 4.0)
        //             ->orderBy('books_count', 'DESC')
        //             ->limit(10);
        //     }, 'cities' => function ($q) {
        //         $q->where('population', '>', 1000000);
        //     }])
        //         ->whereHas('authors', function ($q) {
        //             $q->where('books_count', '>=', 5);
        //         })
        //         ->withCount('authors')
        //         ->having('authors_count', '>=', 3)
        //         ->orderBy('authors_count', 'DESC')
        //         ->limit(5)
        //         ->get()
        //         ->toArray()
        // ];

        // // ================================================================================
        // // TEST 8: Ultra Nested Eager Loading (4 levels)
        // // ================================================================================
        // // Book -> Author -> City -> Country
        // $results['test_8_ultra_nested'] = [
        //     'title' => 'Ultra Nested: Book -> Author -> City -> Country',
        //     'description' => '4 levels nested with complex constraints at each level',
        //     'data' => BookModel::with([
        //         'author' => function ($q) {
        //             $q->where('is_verified', true);
        //         },
        //         'author.city' => function ($q) {
        //             $q->where('population', '>', 500000);
        //         },
        //         'author.city.country' => function ($q) {
        //             $q->where('countries.is_active', true);
        //         },
        //         'publisher',
        //         'categories',
        //         'chapters' => function ($q) {
        //             $q->orderBy('chapter_number', 'ASC')->limit(3);
        //         }
        //     ])
        //         ->whereHas('author.city.country', function ($q) {
        //             $q->where('continent', 'Europe')
        //                 ->where('population', '>', 10000000);
        //         })
        //         ->where('is_bestseller', true)
        //         ->where('rating', '>=', 4.5)
        //         ->orderBy('reviews_count', 'DESC')
        //         ->limit(10)
        //         ->get()
        //         ->toArray()
        // ];

        // // ================================================================================
        // // TEST 9: Complex belongsToMany with Multiple Pivots
        // // ================================================================================
        // // User -> roles -> permissions (double belongsToMany)
        // $results['test_9_double_belongs_to_many'] = [
        //     'title' => 'Double BelongsToMany: Users with Roles and Permissions',
        //     'description' => 'User -> Roles (pivot) -> Permissions (pivot)',
        //     'data' => UserModel::with([
        //         'roles' => function ($q) {
        //             $q->where('roles.is_active', true);
        //         },
        //         'roles.permissions' => function ($q) {
        //             $q->where('permissions.is_active', true);
        //         }
        //     ])
        //         ->whereHas('roles', function ($q) {
        //             $q->where('level', '>=', 5);
        //         })
        //         ->withCount('roles')
        //         ->having('roles_count', '>', 0)
        //         ->limit(10)
        //         ->get()
        //         ->toArray()
        // ];

        // // ================================================================================
        // // TEST 10: hasManyThrough with Intermediate Constraints
        // // ================================================================================
        // // Book -> pages through chapters (with constraints on both)
        // $results['test_10_has_many_through_with_constraints'] = [
        //     'title' => 'hasManyThrough: Book Pages through Chapters',
        //     'description' => 'Book -> Chapters (>1000 words) -> Pages (has_images)',
        //     'data' => BookModel::with([
        //         'pages' => function ($q) {
        //             $q->where('has_images', true)
        //                 ->orderBy('page_number', 'ASC')
        //                 ->limit(10);
        //         },
        //         'chapters' => function ($q) {
        //             $q->where('words_count', '>', 1000)
        //                 ->orderBy('chapter_number', 'ASC');
        //         },
        //         'author'
        //     ])
        //         ->whereHas('chapters', function ($q) {
        //             $q->where('is_free_preview', true);
        //         })
        //         ->whereHas('pages', function ($q) {
        //             $q->where('words_count', '>', 500);
        //         })
        //         ->withCount(['pages', 'chapters'])
        //         ->having('pages_count', '>=', 50)
        //         ->orderBy('pages_count', 'DESC')
        //         ->limit(5)
        //         ->get()
        //         ->toArray()
        // ];

        // // ================================================================================
        // // TEST 11: Mixed Relationships Ultra Complex
        // // ================================================================================
        // $results['test_11_mixed_ultra_complex'] = [
        //     'title' => 'ULTRA COMPLEX: All relationship types combined',
        //     'description' => 'Combining hasMany, belongsTo, hasManyThrough, belongsToMany',
        //     'data' => CountryModel::with([
        //         // hasMany
        //         'cities' => function ($q) {
        //             $q->where('is_capital', true)
        //                 ->orWhere('population', '>', 5000000);
        //         },
        //         // hasManyThrough level 1
        //         'cities.authors' => function ($q) {
        //             $q->where('is_verified', true)
        //                 ->where('rating', '>=', 4.0);
        //         },
        //         // hasMany through hasManyThrough
        //         'cities.authors.books' => function ($q) {
        //             $q->where('is_available', true)
        //                 ->where('stock', '>', 0);
        //         },
        //         // belongsToMany through hasManyThrough
        //         'cities.authors.books.categories' => function ($q) {
        //             $q->wherePivot('is_primary', true);
        //         },
        //         // hasMany through multiple levels
        //         'cities.authors.books.chapters' => function ($q) {
        //             $q->where('is_free_preview', true)
        //                 ->limit(2);
        //         },
        //         // hasMany
        //         'publishers' => function ($q) {
        //             $q->where('publishers.is_active', true);
        //         },
        //     ])
        //         ->whereHas('cities.authors.books', function ($q) {
        //             $q->where('rating', '>=', 4.5)
        //                 ->where('reviews_count', '>', 100);
        //         })
        //         ->withCount([
        //             'cities',
        //             'authors',
        //             'publishers'
        //         ])
        //         ->having('cities_count', '>=', 3)
        //         ->having('authors_count', '>=', 10)
        //         ->orderBy('authors_count', 'DESC')
        //         ->limit(3)
        //         ->get()
        //         ->toArray()
        // ];

        // // ================================================================================
        // // TEST 12: Conditional Eager Loading
        // // ================================================================================
        // $loadPublisher = $request->get('load_publisher', 'true') === 'true';
        // $minRating = (float) $request->get('min_rating', 4.0);

        // $query = BookModel::with(['author.city.country']);

        // if ($loadPublisher) {
        //     $query->with('publisher');
        // }

        // $results['test_12_conditional_loading'] = [
        //     'title' => 'Conditional Eager Loading',
        //     'description' => 'Load relationships based on query parameters',
        //     'data' => $query->where('rating', '>=', $minRating)
        //         ->where('is_available', true)
        //         ->limit(10)
        //         ->get()
        //         ->toArray()
        // ];
        //
        // Get query log
        $queries = QB::getQueryLog();

        return response()->json([
            'success' => true,
            'message' => 'All relationship tests executed successfully',
            'total_tests' => count($results),
            'total_queries' => count($queries),
            'data' => $results,
            'queries' => array_map(function ($q) {
                return [
                    'sql' => $q['query'],
                    'bindings' => $q['bindings'],
                    'time' => $q['time'] ?? 0
                ];
            }, $queries)
        ]);
    }

    /**
     * Test hasOne relationship with complex constraints
     *
     * GET /api/relationships/has-one
     */
    public function testHasOne(Request $request)
    {
        $data = AuthorModel::with(['latestBook', 'bestsellerBook', 'city.country'])
            ->where('is_verified', true)
            ->whereHas('latestBook')
            ->orderBy('rating', 'DESC')
            ->limit(20)
            ->get()
            ->toArray();

        return response()->json([
            'success' => true,
            'title' => 'hasOne: Author with Latest & Bestseller Book',
            'data' => $data
        ]);
    }

    /**
     * Test hasMany relationship with aggregates
     *
     * GET /api/relationships/has-many
     */
    public function testHasMany(Request $request)
    {
        $data = AuthorModel::with(['books' => function ($q) {
            $q->where('is_available', true)
                ->orderBy('published_year', 'DESC');
        }])
            ->withCount(['books' => function ($q) {
                $q->where('is_available', true);
            }])
            ->withAvg('books', 'rating')
            ->withSum('books', 'stock')
            ->having('books_count', '>=', 3)
            ->having('books_avg_rating', '>=', 4.0)
            ->orderBy('books_count', 'DESC')
            ->limit(15)
            ->get()
            ->toArray();

        return response()->json([
            'success' => true,
            'title' => 'hasMany: Authors with Books (with aggregates)',
            'data' => $data
        ]);
    }

    /**
     * Test hasManyThrough with ultra complex constraints
     *
     * GET /api/relationships/has-many-through
     */
    public function testHasManyThrough(Request $request)
    {
        // Test multiple hasManyThrough relationships
        $data = [
            // City -> Books through Authors
            'city_books' => CityModel::with([
                'books.author',
                'books.chapters' => function ($q) {
                    $q->orderBy('chapter_number', 'ASC')->limit(3);
                }
            ])
                ->whereHas('books', function ($q) {
                    $q->where('rating', '>=', 4.5)
                        ->where('is_bestseller', true);
                })
                ->withCount('books')
                ->withAvg('books', 'rating')
                ->having('books_count', '>=', 5)
                ->orderBy('books_avg_rating', 'DESC')
                ->limit(10)
                ->get()
                ->toArray(),

            // Country -> Authors through Cities
            'country_authors' => CountryModel::with([
                'authors.books' => function ($q) {
                    $q->where('is_bestseller', true)
                        ->orderBy('rating', 'DESC')
                        ->limit(3);
                }
            ])
                ->whereHas('authors', function ($q) {
                    $q->where('is_verified', true)
                        ->where('books_count', '>=', 5);
                })
                ->withCount('authors')
                ->withAvg('authors', 'rating')
                ->having('authors_count', '>=', 10)
                ->orderBy('authors_avg_rating', 'DESC')
                ->limit(5)
                ->get()
                ->toArray(),
        ];

        return response()->json([
            'success' => true,
            'title' => 'hasManyThrough: Multiple complex examples',
            'data' => $data
        ]);
    }

    /**
     * Test belongsToMany with complex pivot constraints
     *
     * GET /api/relationships/belongs-to-many
     */
    public function testBelongsToMany(Request $request)
    {
        $data = [
            // Books with categories
            'books_categories' => BookModel::with(['categories' => function ($q) {
                $q->wherePivot('is_primary', true)
                    ->orderByPivot('order', 'ASC');
            }, 'author', 'publisher'])
                ->whereHas('categories', function ($q) {
                    $q->where('categories.is_active', true);
                }, '>=', 2)
                ->withCount('categories')
                ->having('categories_count', '>=', 2)
                ->limit(15)
                ->get()
                ->toArray(),

            // Users with roles and permissions
            'users_roles_permissions' => UserModel::with([
                'roles' => function ($q) {
                    $q->where('roles.is_active', true)
                        ->orderBy('level', 'DESC');
                },
                'roles.permissions' => function ($q) {
                    $q->where('permissions.is_active', true)
                        ->orderBy('resource', 'ASC');
                }
            ])
                ->whereHas('roles', function ($q) {
                    $q->where('level', '>=', 5);
                })
                ->withCount(['roles', 'roles.permissions'])
                ->having('roles_count', '>', 0)
                ->limit(10)
                ->get()
                ->toArray(),
        ];

        return response()->json([
            'success' => true,
            'title' => 'belongsToMany: Complex pivot constraints',
            'data' => $data
        ]);
    }
}
