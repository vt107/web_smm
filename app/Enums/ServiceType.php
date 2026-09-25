<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Str;

/**
 * Loại dịch vụ theo chuẩn PerfectPanel. Mỗi loại quyết định tham số bắt buộc khi đặt đơn
 * (lưu vào orders.extra) và cách tính số lượng.
 */
enum ServiceType: string implements HasLabel
{
    case Default = 'default';
    case Package = 'package';
    case CustomComments = 'custom_comments';
    case CustomCommentsPackage = 'custom_comments_package';
    case MentionsCustomList = 'mentions_custom_list';
    case MentionsWithHashtags = 'mentions_with_hashtags';
    case MentionsUserFollowers = 'mentions_user_followers';
    case CommentLikes = 'comment_likes';
    case CommentReplies = 'comment_replies';
    case Poll = 'poll';
    case WebTraffic = 'web_traffic';

    public function label(): string
    {
        return match ($this) {
            self::Default => 'Default',
            self::Package => 'Package',
            self::CustomComments => 'Custom Comments',
            self::CustomCommentsPackage => 'Custom Comments Package',
            self::MentionsCustomList => 'Mentions Custom List',
            self::MentionsWithHashtags => 'Mentions with Hashtags',
            self::MentionsUserFollowers => 'Mentions User Followers',
            self::CommentLikes => 'Comment Likes',
            self::CommentReplies => 'Comment Replies',
            self::Poll => 'Poll',
            self::WebTraffic => 'Web Traffic',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    /** Gói cố định: không nhập số lượng, giá = rate (không chia 1000). */
    public function isPackage(): bool
    {
        return in_array($this, [self::Package, self::CustomCommentsPackage], true);
    }

    /** Tham số mà số lượng được đếm từ số dòng (thay cho `quantity`). */
    public function quantityFromLinesOf(): ?string
    {
        return match ($this) {
            self::CustomComments, self::CommentReplies => 'comments',
            self::MentionsCustomList => 'usernames',
            default => null,
        };
    }

    public function supportsDripfeed(): bool
    {
        return $this === self::Default;
    }

    /**
     * Rule validate cho các tham số riêng của loại dịch vụ.
     *
     * @return array<string, list<string>>
     */
    public function extraRules(): array
    {
        return match ($this) {
            self::Default, self::Package => [],
            self::CustomComments, self::CustomCommentsPackage => [
                'comments' => ['required', 'string', 'max:20000'],
            ],
            self::MentionsCustomList => [
                'usernames' => ['required', 'string', 'max:20000'],
            ],
            self::MentionsWithHashtags => [
                'usernames' => ['required', 'string', 'max:20000'],
                'hashtags' => ['required', 'string', 'max:5000'],
            ],
            self::MentionsUserFollowers, self::CommentLikes => [
                'username' => ['required', 'string', 'max:191'],
            ],
            self::CommentReplies => [
                'username' => ['required', 'string', 'max:191'],
                'comments' => ['required', 'string', 'max:20000'],
            ],
            self::Poll => [
                'answer_number' => ['required', 'integer', 'min:1'],
            ],
            self::WebTraffic => [
                'country' => ['nullable', 'string', 'max:50'],
                'device' => ['nullable', 'string', 'max:50'],
                'type_of_traffic' => ['nullable', 'string', 'max:50'],
                'google_keyword' => ['nullable', 'string', 'max:255'],
                'referring_url' => ['nullable', 'url', 'max:1000'],
            ],
        };
    }

    /**
     * Mô tả ô nhập thêm cho form đặt đơn trên web (khớp với extraRules()).
     *
     * @return list<array{name: string, label: string, input: string, required: bool, hint: ?string}>
     */
    public function formFields(): array
    {
        $field = fn (string $name, string $label, string $input = 'text', bool $required = true, ?string $hint = null) => compact('name', 'label', 'input', 'required', 'hint');

        return match ($this) {
            self::Default, self::Package => [],
            self::CustomComments, self::CustomCommentsPackage => [
                $field('comments', 'Danh sách bình luận', 'textarea', hint: 'Mỗi dòng 1 bình luận'),
            ],
            self::MentionsCustomList => [
                $field('usernames', 'Danh sách username', 'textarea', hint: 'Mỗi dòng 1 username'),
            ],
            self::MentionsWithHashtags => [
                $field('usernames', 'Danh sách username', 'textarea', hint: 'Mỗi dòng 1 username'),
                $field('hashtags', 'Hashtag', 'textarea', hint: 'Mỗi dòng 1 hashtag'),
            ],
            self::MentionsUserFollowers, self::CommentLikes => [
                $field('username', 'Username'),
            ],
            self::CommentReplies => [
                $field('username', 'Username'),
                $field('comments', 'Danh sách trả lời', 'textarea', hint: 'Mỗi dòng 1 câu trả lời'),
            ],
            self::Poll => [
                $field('answer_number', 'Số thứ tự đáp án', 'number'),
            ],
            self::WebTraffic => [
                $field('country', 'Quốc gia', required: false),
                $field('device', 'Thiết bị', required: false),
                $field('type_of_traffic', 'Loại traffic', required: false),
                $field('google_keyword', 'Từ khoá Google', required: false),
                $field('referring_url', 'URL giới thiệu', required: false),
            ],
        };
    }

    /** Nhận diện type provider trả về (vd "Custom Comments"), không nhận ra thì coi là Default. */
    public static function fromProvider(?string $type): self
    {
        return self::tryFrom(Str::snake(strtolower(trim((string) $type)))) ?? self::Default;
    }
}
