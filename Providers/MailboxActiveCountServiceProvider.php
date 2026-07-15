<?php

namespace Modules\MailboxActiveCount\Providers;

use Illuminate\Support\ServiceProvider;
use App\Conversation;

class MailboxActiveCountServiceProvider extends ServiceProvider
{
    /**
     * Cache TTL in minutes (Laravel 5.5's Cache::remember() expects minutes).
     */
    const CACHE_MINUTES = 1;

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->hooks();
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Module hooks.
     */
    public function hooks()
    {
        // Multi-mailbox dropdown: one call per mailbox <a> tag.
        \Eventy::addAction('menu.mailbox.after_name', array($this, 'showBadgeInDropdown'), 10, 1);

        // Single-mailbox case (no dropdown, just the "Mailbox" link).
        \Eventy::addAction('menu.mailbox_single.after_name', array($this, 'showBadgeSingle'), 10, 1);

        // Inline styles for the badge, printed once in <head>.
        \Eventy::addAction('layout.head', array($this, 'printStyles'));
    }

    /**
     * Print the badge styles. Injected inline (rather than as a published
     * module asset) so it works even before the module's public symlink
     * exists, matching how core itself guards against that in app.blade.php.
     *
     * @return void
     */
    public function printStyles()
    {
        echo <<<'HTML'
<style>
    /* Push the badge to the right edge of the mailbox dropdown, regardless
       of how long the mailbox name is (float:right alone doesn't do this,
       since the <a> only grows as wide as its own content). */
    .dropdown-menu.dm-scrollable > li > a {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .mailboxactivecount-badge {
        display: inline-block;
        min-width: 20px;
        height: 20px;
        margin-left: 10px;
        padding: 0 7px;
        background-color: #85919e;
        color: #fff;
        font-size: 11px;
        font-weight: bold;
        line-height: 20px;
        text-align: center;
        border-radius: 18px;
        flex-shrink: 0;
    }
</style>
HTML;
    }

    /**
     * Output the badge for a mailbox row inside the dropdown menu.
     *
     * @param \App\Mailbox $mailbox
     * @return void
     */
    public function showBadgeInDropdown($mailbox)
    {
        echo $this->renderBadge($mailbox);
    }

    /**
     * Output the badge next to the single "Mailbox" link.
     *
     * @param \App\Mailbox $mailbox
     * @return void
     */
    public function showBadgeSingle($mailbox)
    {
        echo $this->renderBadge($mailbox);
    }

    /**
     * Build the badge HTML for the given mailbox, or an empty string if
     * there are no active conversations.
     *
     * @param \App\Mailbox $mailbox
     * @return string
     */
    protected function renderBadge($mailbox)
    {
        if (!$mailbox || !$mailbox->id) {
            return '';
        }

        $count = $this->getActiveCount($mailbox);

        if ($count < 1) {
            return '';
        }

        return ' <span class="mailboxactivecount-badge">'.(int) $count.'</span>';
    }

    /**
     * Get the number of active (status=active, state=published) conversations
     * for a mailbox, cached briefly to avoid a COUNT(*) query per mailbox on
     * every authenticated page load (the navbar renders on every page).
     *
     * @param \App\Mailbox $mailbox
     * @return int
     */
    protected function getActiveCount($mailbox)
    {
        $cache_key = 'mailboxactivecount_active_'.$mailbox->id;

        return \Cache::remember($cache_key, self::CACHE_MINUTES, function () use ($mailbox) {
            return Conversation::where('mailbox_id', $mailbox->id)
                ->where('state', Conversation::STATE_PUBLISHED)
                ->where('status', Conversation::STATUS_ACTIVE)
                ->count();
        });
    }
}
