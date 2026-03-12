@echo off
cd /d %~dp0
python -m pip install -r requirements.txt
python generate_social_gifs.py
pause
