=== Skydrive Directlink ===
Contributors: Flarefox
Donate link: 
Tags: skydrive,direct, link
Requires at least: 2.7
Tested up to: 3.1
Stable tag: 0.7.0

Auto update the direct link of skydrive file. 自动获得skydrive外链。

== Description ==
(中文说明请往下翻，辛苦了)

Skydrive is an excellent net disk, but we have to open a skydrive web page to download a file. This plugin purposes to get the direct link of a skydrive file, so that it can be directly downloaded anywhere. 

Usage example: 

1. In a link: `<a href="[skydrive:http://*****.skydrive.live.com/.../somefile.zip]">somefile.zip</a>`

2. With Audio Player plugin: `[audio:[skydrive:http://*****.skydrive.live.com/.../somemusic.mp3]]`

3. After set a default skydrive url, for example `http://cid-955fceff19f67540.skydrive.live.com/`, you can use `[skydrive:self.aspx/.Public/howdy.txt]` to simplify `[skydrive:http://cid-955fceff19f67540.skydrive.live.com/self.aspx/.Public/howdy.txt]`.

There are 3 cache modes:

1. no cache. Slow but the most accurate.

2. cache and validate. It keeps accuracy while spends less time.

3. cache only. RECOMMENDED when automatically update is enabled.

* Cache is located in a database table like `wp_skydrive_directlink`. *

Find more information in FAQ section.

If you have any question or suggestion, please response here or mail to flarefox at 163 dot com. That will help me a lot, thank you!

------------------中文说明--------------------

Skydrive是一个优秀的网盘，但是它不支持外链。本插件的目的就是自动生成和更新skydrive文件的外链。

使用示例：

1. 生成一个链接：`<a href="[skydrive:http://*****.skydrive.live.com/.../somefile.zip]">somefile.zip</a>`

2. 使用Audio Player插件播放音乐：`[audio:[skydrive:http://*****.skydrive.live.com/.../somemusic.mp3]]`

3. 如果插件中已设置默认skydrive链接例如`http://cid-955fceff19f67540.skydrive.live.com/`，可以用`[skydrive:self.aspx/.Public/howdy.txt]`作为`[skydrive:http://cid-955fceff19f67540.skydrive.live.com/self.aspx/.Public/howdy.txt]`的简化。

设置页面中有三种缓存模式：

1. 不缓存。最慢但是最准确。

2. 缓存且验证。保持准确度同时花费更少时间。

3. 仅缓存。当外链自动更新功能被启用时，推荐用此模式，非常节省时间。

* 缓存被存放在一个形如“wp_skydrive_directlink”的数据库表中。 *

到FAQ页面寻找更多的说明信息。

如果您有任何建议，请发信到flarefox@163.com。您的建议对我很重要，谢谢！

== Installation ==

1. Upload `skydrive-directlink` folder to the `/wp-content/plugins/` directory

2. Activate the plugin through the 'Plugins' menu in WordPress

3. Use [skydrive:skydrive file download page url] to get direct link.

------------------中文说明--------------------

1. 上传`skydrive-directlink`目录到wordpress的`/wp-content/plugins/`目录

2. 在wordpress后台激活此插件

3. 在文章中使用[skydrive:skydrive文件的官方链接]以获得文件外链。

== Screenshots ==

1. Option page

== Frequently Asked Questions ==

= What is direct link? =
= 什么是外链? =

When click the link, you can download the file directly, instead of being lead to a download page.

当点击链接时，你可以直接下载这个文件，而不是打开一个下载页面。这样的链接叫外链。

= How does this plugin work? =
= 这个插件是怎样工作的? =

It analyzes skydrive download page and get the direct link when a post is load.

当博客的一篇文章被打开时，此插件将文中的skydrive的官方链接替换为外链。

= Will it slow down my site? =
= 这个插件会让我的站点变慢吗? =

After v0.4.11, links can be cached in a database table, which greatly reduce the load time. 

You can find three cache modes in option page. If cache is disabled, the plugin have to analyze skydrive download page first, which surely needs some time. The time depends on the connecting speed between your host and skydrive site.

In my host, the time to get a direct link is:

No cache: 2-4 seconds.

Cache and validate: 0.3-0.6 seconds.

Only cache: 0.001 seconds.

0.4.11版引入了缓存机制，页面载入时间大大减少了。

你可以在设置页看到三种缓存模式。如果不是`仅缓存`模式，此插件必须每次都分析skydrive的页面，这需要一定的时间。具体时间多少依赖于你的站点和微软skydrive之间的网络速度。

下面这些数据，是在我的主机上生成一个外链的耗时：

禁用缓存：2秒-4秒

缓存并验证：0.3-0.6秒

仅缓存：0.001秒

= Automatically Update? =
= 自动更新? =

Since v0.5, skydrive links can be updated automatically and periodically. For the limitation of wordpress cron scheme, your site needs a visit to trigger the update.

从0.5版开始，skydrive链接可以在后台定期自动更新。例如设定周期为8小时，则每隔8小时，所有skydrive的链接会自动更新一次。

= 2011年6月下旬微软更新链接格式 =

微软修改了skydrive链接页的格式，今后我们必须换用新的格式，但旧的链接格式仍然被支持（暂时）。
旧的链接格式例如：`http://cid-955fceff19f67540.skydrive.live.com/self.aspx/.Public/howdy.txt`
新的链接格式为：`https://skydrive.live.com/?cid=955fceff19f67540&id=955FCEFF19F67540%21110`
新的链接格式必须在浏览器页面中用右键菜单->复制快捷方式（链接地址）获得。

== Changelog ==
= 0.7.0 =
* There are two new Skydrive link formats. But V0.6.9 only supports one. The other format is added in this version.
* 微软的新链接格式有两种，但0.6.9版中只支持了一种，此版本加入另一种。

= 0.6.9 =
* Fixed a serious bug.
* 修订了一个严重bug（php文件的开头多了个空格……），感谢熏子(http://kuyur.info)告知。

= 0.6.8 =
* This update is only because Skydrive changed its link format.
* 此次更新仅因为Skydrive修改了下载链接的格式。感谢Ziyo（http://www.xuwen.name）告知。

= 0.6.5 =
* Support SAE(Sina Application Engine) now. 
* If you could not get a direct url before, try this version, which probably works now.
* 支持SAE（新浪应用引擎）。
* 如果你以前用这个插件无法得到外链，不妨试试这个版本。

= 0.5.61 =
* Skydrive directlinks can be updated automatically and periodically. The default period is `1 day`.
* Recommended cache modes is `cache only` now.
* You can manually update all links in the option page. Notice that `Update Now` in fact means `Update after 1 minute`.
* Some small changes.
* Skydrive外链可以被自动定期更新。默认的周期为`1天`。
* 新装此插件时，默认的缓存模式改为“仅缓存”。
* 你也可以在设置页面手动更新所有链接。注意“立即更新”按钮的含义其实是“一分钟后更新”。

= 0.4.20 =
* Remove `?download` from direct url.

= 0.4.13 =
* This update is only because Microsoft changed its skydrive page source code.

= 0.4.11 =
* Add a db table to cache links.
* Add three cache modes: no cache, cache and validate, cache only.
* Modify some items in option page. 

= 0.3 =
* Add skydrive load time test in option page.
* Fix some bugs on option page.

= 0.2 =
* Change the main algorithm.

= 0.1 =
* Works on wordpress 2.8 and 2.7.
