<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use Toporia\Framework\Container\Contracts\ContainerInterface;
use Toporia\Framework\Foundation\ServiceProvider;

// Domain Repository Contracts
use App\Domain\Contracts\Repository\PostRepository;
use App\Domain\Contracts\Repository\CommentRepository;
use App\Domain\Contracts\Repository\CategoryRepository;
use App\Domain\Contracts\Repository\TagRepository;
use App\Domain\Contracts\Repository\FeedbackRepository;
use App\Domain\Contracts\Repository\UserRepository;

// Infrastructure Repository Implementations
use App\Infrastructure\Repository\PdoPostRepository;
use App\Infrastructure\Repository\PdoCommentRepository;
use App\Infrastructure\Repository\PdoCategoryRepository;
use App\Infrastructure\Repository\PdoTagRepository;
use App\Infrastructure\Repository\PdoFeedbackRepository;

// Blog Services
use App\Application\Services\Blog\PostService;
use App\Application\Services\Blog\CommentService;
use App\Application\Services\Blog\CategoryService;
use App\Application\Services\Blog\TagService;
use App\Application\Services\Blog\FeedbackService;

// Admin Services
use App\Application\Services\Admin\DashboardService;
use App\Application\Services\Admin\PostAdminService;
use App\Application\Services\Admin\CommentAdminService;
use App\Application\Services\Admin\CategoryAdminService;
use App\Application\Services\Admin\TagAdminService;
use App\Application\Services\Admin\UserAdminService;
use App\Application\Services\Admin\SettingsService;
use App\Application\Services\Admin\FeedbackAdminService;

// Admin Repositories
use App\Infrastructure\Repository\Admin\PostAdminRepository;
use App\Infrastructure\Repository\Admin\CommentAdminRepository;
use App\Infrastructure\Repository\Admin\CategoryAdminRepository;
use App\Infrastructure\Repository\Admin\TagAdminRepository;
use App\Infrastructure\Repository\Admin\UserAdminRepository;
use App\Infrastructure\Repository\Admin\FeedbackAdminRepository;

/**
 * Blog Service Provider
 *
 * Registers all blog-related repositories and services.
 * Follows Clean Architecture by binding interfaces to implementations.
 */
class BlogServiceProvider extends ServiceProvider
{
    /**
     * Register services into the container.
     *
     * @param ContainerInterface $container
     * @return void
     */
    public function register(ContainerInterface $container): void
    {
        // Bind Repository Interfaces to Implementations
        $this->registerRepositories($container);

        // Register Application Services
        $this->registerBlogServices($container);
        $this->registerAdminServices($container);
    }

    /**
     * Register repository bindings.
     *
     * @param ContainerInterface $container
     * @return void
     */
    protected function registerRepositories(ContainerInterface $container): void
    {
        // PostRepository -> PdoPostRepository
        // Note: Repositories use ORM Models which handle DB connections internally
        $container->singleton(PostRepository::class, function () {
            return new PdoPostRepository();
        });

        // CommentRepository -> PdoCommentRepository
        $container->singleton(CommentRepository::class, function () {
            return new PdoCommentRepository();
        });

        // CategoryRepository -> PdoCategoryRepository
        $container->singleton(CategoryRepository::class, function () {
            return new PdoCategoryRepository();
        });

        // TagRepository -> PdoTagRepository
        $container->singleton(TagRepository::class, function () {
            return new PdoTagRepository();
        });

        // FeedbackRepository -> PdoFeedbackRepository
        $container->singleton(FeedbackRepository::class, function () {
            return new PdoFeedbackRepository();
        });
    }

    /**
     * Register blog (public site) services.
     *
     * @param ContainerInterface $container
     * @return void
     */
    protected function registerBlogServices(ContainerInterface $container): void
    {
        // PostService
        $container->singleton(PostService::class, function ($c) {
            return new PostService(
                $c->get(PostRepository::class),
                $c->get(TagRepository::class),
                $c->get(CategoryRepository::class),
                $c->get(UserRepository::class)
            );
        });

        // CommentService
        $container->singleton(CommentService::class, function ($c) {
            return new CommentService(
                $c->get(CommentRepository::class),
                $c->get(PostRepository::class)
            );
        });

        // CategoryService
        $container->singleton(CategoryService::class, function ($c) {
            return new CategoryService(
                $c->get(CategoryRepository::class)
            );
        });

        // TagService
        $container->singleton(TagService::class, function ($c) {
            return new TagService(
                $c->get(TagRepository::class)
            );
        });

        // FeedbackService
        $container->singleton(FeedbackService::class, function ($c) {
            return new FeedbackService(
                $c->get(FeedbackRepository::class)
            );
        });
    }

    /**
     * Register admin services.
     *
     * @param ContainerInterface $container
     * @return void
     */
    protected function registerAdminServices(ContainerInterface $container): void
    {
        // Register Admin Repositories
        $container->singleton(PostAdminRepository::class, fn() => new PostAdminRepository());
        $container->singleton(CommentAdminRepository::class, fn() => new CommentAdminRepository());
        $container->singleton(CategoryAdminRepository::class, fn() => new CategoryAdminRepository());
        $container->singleton(TagAdminRepository::class, fn() => new TagAdminRepository());
        $container->singleton(UserAdminRepository::class, fn() => new UserAdminRepository());
        $container->singleton(FeedbackAdminRepository::class, fn() => new FeedbackAdminRepository());

        // DashboardService
        $container->singleton(DashboardService::class, function ($c) {
            return new DashboardService(
                $c->get(PostRepository::class),
                $c->get(CommentRepository::class),
                $c->get(FeedbackRepository::class)
            );
        });

        // PostAdminService
        $container->singleton(PostAdminService::class, function ($c) {
            return new PostAdminService(
                $c->get(PostAdminRepository::class)
            );
        });

        // CommentAdminService
        $container->singleton(CommentAdminService::class, function ($c) {
            return new CommentAdminService(
                $c->get(CommentAdminRepository::class)
            );
        });

        // CategoryAdminService
        $container->singleton(CategoryAdminService::class, function ($c) {
            return new CategoryAdminService(
                $c->get(CategoryAdminRepository::class)
            );
        });

        // TagAdminService
        $container->singleton(TagAdminService::class, function ($c) {
            return new TagAdminService(
                $c->get(TagAdminRepository::class)
            );
        });

        // UserAdminService
        $container->singleton(UserAdminService::class, function ($c) {
            return new UserAdminService(
                $c->get(UserAdminRepository::class)
            );
        });

        // SettingsService (no dependencies)
        $container->singleton(SettingsService::class, function () {
            return new SettingsService();
        });

        // FeedbackAdminService
        $container->singleton(FeedbackAdminService::class, function ($c) {
            return new FeedbackAdminService(
                $c->get(FeedbackAdminRepository::class)
            );
        });
    }

    /**
     * Bootstrap services after registration.
     *
     * @param ContainerInterface $container
     * @return void
     */
    public function boot(ContainerInterface $container): void
    {
        // Any boot logic can go here
        // For example: registering model observers, loading config, etc.
    }
}
