@echo off
if not exist runtime\uploads mkdir runtime\uploads
if not exist runtime\sessions mkdir runtime\sessions
if not exist runtime\tmp mkdir runtime\tmp
php -d upload_tmp_dir=runtime\uploads -d session.save_path=runtime\sessions -d sys_temp_dir=runtime\tmp -d upload_max_filesize=5M -d post_max_size=6M -S 127.0.0.1:8010 -t web
