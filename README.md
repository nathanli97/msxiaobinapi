## MSXiaoBinApi : 这是一个使用PHP封装的微软小冰API

> 实际上MSXiaoBinApi 通过新浪微博私信方式通讯的.因此在使用本脚本时，请确保你的微博已经领养了一只微软小冰。

### 运行环境要求：
- PHP >=5.3
- Workerman >=3.0

## Quick Start

1. 下载Workerman框架
2. 将下载好的框架解压到workerman文件夹.
3. 使用Chrome/Firefox浏览器打开新浪微博，进入 我的关注-》微软小冰-》私信聊天页面。
4. 按F12打开调试工具。刷新页面，再"Network"标签页点击第一个连接,在"Request Headers" Cookie信息复制下来.
5. 将Cookie保存到脚本目录的cookies.txt。
6. 使用命令```php main.php --generate```生成一个配置文件。
7. 使用命令```php main.php```启动脚本。
8. 使用WebSocket协议 通过127.0.0.1:50357与小冰通信。

注：你可以使用Chrome插件WebSocket Test Client来测试通信。

##已知问题
目前此脚本可以接收小冰发来的文字及图片(图片通过BASE64编码后传递)，但是向小冰发送消息目前只能使用纯文本。
