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

        // Cache invalidation: clear the affected mailbox's cached count
        // whenever something changes which conversations count as active
        // (status change, move to/from trash, new conversation). The 1
        // minute TTL in getActiveCount() remains as a safety net for any
        // path not covered below.
        \Eventy::addAction('conversation.status_changed', array($this, 'clearCacheForConversation'), 20, 1);
        \Eventy::addAction('conversation.state_changed', array($this, 'clearCacheForConversation'), 20, 1);
        \Eventy::addAction('conversation.created_by_customer', array($this, 'clearCacheForConversation'), 20, 1);
        \Eventy::addAction('conversation.created_by_user_can_undo', array($this, 'clearCacheForConversation'), 20, 1);
        \Eventy::addAction('conversation.moved', array($this, 'clearCacheForMovedConversation'), 20, 3);
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
        return \Cache::remember($this->cacheKey($mailbox->id), self::CACHE_MINUTES, function () use ($mailbox) {
            return Conversation::where('mailbox_id', $mailbox->id)
                ->where('state', Conversation::STATE_PUBLISHED)
                ->where('status', Conversation::STATUS_ACTIVE)
                ->count();
        });
    }

    /**
     * Build the cache key used to store a mailbox's active count.
     *
     * @param int $mailbox_id
     * @return string
     */
    protected function cacheKey($mailbox_id)
    {
        return 'mailboxactivecount_active_'.$mailbox_id;
    }

    /**
     * Clear the cached active count for a conversation's mailbox. Hooked to
     * events that can change which conversations count as active: status
     * changes, moves to/from the trash (state changes), and new
     * conversations (customer- or agent-created).
     *
     * @param \App\Conversation $conversation
     * @return void
     */
    public function clearCacheForConversation($conversation)
    {
        if ($conversation && $conversation->mailbox_id) {
            \Cache::forget($this->cacheKey($conversation->mailbox_id));
        }
    }

    /**
     * Clear the cached active count for both mailboxes involved when a
     * conversation is moved from one mailbox to another.
     *
     * @param \App\Conversation $conversation Conversation, already in its new mailbox.
     * @param \App\User $user
     * @param \App\Mailbox $prev_mailbox
     * @return void
     */
    public function clearCacheForMovedConversation($conversation, $user, $prev_mailbox)
    {
        $this->clearCacheForConversation($conversation);

        if ($prev_mailbox && $prev_mailbox->id) {
            \Cache::forget($this->cacheKey($prev_mailbox->id));
        }
    }
}
