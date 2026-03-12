@echo off
echo Committing and pushing...
cd /d c:\Users\Hp\Desktop\PhpstormProjects\scout_api_pr_clean
git add .
git commit -m "feat: add quick start scripts for easy setup"
git push origin main
echo.
echo Done! Now double-click START.bat to run the server
pause
