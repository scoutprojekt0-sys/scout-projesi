@echo off
echo ================================
echo Scout API Quick Test
echo ================================
echo.

cd /d c:\Users\Hp\Desktop\PhpstormProjects\scout_api_pr_clean

echo Testing API endpoints...
echo.

echo [1] Testing /api/ping...
curl -s http://localhost:8000/api/ping
echo.
echo.

echo [2] Testing /api/news...
curl -s http://localhost:8000/api/news
echo.
echo.

echo [3] Testing /api/billing/plans...
curl -s http://localhost:8000/api/billing/plans
echo.
echo.

echo [4] Testing /api/public/players...
curl -s http://localhost:8000/api/public/players
echo.
echo.

echo ================================
echo Test Complete!
echo ================================
pause
