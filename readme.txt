=== RSS make antenna ===
Contributors: issey7
Tags: rss feed
Requires at least: 4.7
Tested up to: 5.8
Stable tag: 1.7.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin gets update information of the site from RSS and posts it.

== Description ==

RSSからサイトの更新情報を取得して投稿するプラグインです。

=== Features of the this version ===
*取得した情報１つにつき１つの記事を自動作成します。
*アイキャッチ画像を表示できます。

== Installation ==

1. Upload `rss-make-antenna.zip` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Appear under Settings menu.
4. Please enter the feed url you want to visit.
5. Click save.

== Changelog ==

= 1.7.2 =
「このサイトの記事を見る」を追加する・しないする様に修正しました。

= 1.7.1 =
moreタグの付け方を修正しました。

= 1.7 =
巡回するサイト名をタグからカテゴリーに変えられる様にしました。

= 1.6.2 =
アイキャッチ画像のIDを削除できないように修正しました。

= 1.6.1 =
1.6の時に、readme.txtの修正忘れのためバージョンアップしました。

= 1.6 =
指定した日付以前の記事を作成しないようにしました。
記事一覧でクリックした時、直接記事のサイトにジャンプするか、RSS取得で作成した記事にジャンプできるようにしました。

= 1.5 =
fixed filter bug of the_permalink function.

= 1.4 =
Get multiple categories.

= 1.3 =
Changed the number of characters of excerpted text.

= 1.2 =
Add excerpt of text to article.

= 1.1 =
Change how to check the article.

= 1.0 =
First release.
