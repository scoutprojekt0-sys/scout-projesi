Set oWS = WScript.CreateObject("WScript.Shell")
sLinkFile = "C:\Users\Hp\Desktop\NextScout - Start Server.lnk"
Set oLink = oWS.CreateShortcut(sLinkFile)
oLink.TargetPath = "C:\Users\Hp\Desktop\PhpstormProjects\untitled\QUICK_START.bat"
oLink.WorkingDirectory = "C:\Users\Hp\Desktop\PhpstormProjects\untitled\scout_api"
oLink.Description = "NextScout Server Baslat"
oLink.IconLocation = "C:\Windows\System32\shell32.dll,165"
oLink.Save
WScript.Echo "Kisayol olusturuldu: Masaustunde 'NextScout - Start Server'"
