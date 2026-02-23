import 'package:flutter/material.dart';

void main() => runApp(ScoutApp());

class ScoutApp extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      debugShowCheckedModeBanner: false,
      theme: ThemeData.dark().copyWith(
        primaryColor: Color(0xFF1DB954), // Futbol sahası yeşili
        scaffoldBackgroundColor: Color(0xFF0A0E21),
      ),
      home: HomePage(),
    );
  }
}

class HomePage extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('SCOUT VİTRİN', style: TextStyle(fontWeight: FontWeight.bold, letterSpacing: 2)),
        centerTitle: true,
        actions: [IconButton(icon: Icon(Icons.filter_list), onPressed: () {})],
        backgroundColor: Colors.transparent,
        elevation: 0,
      ),
      body: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text("Öne Çıkan Yetenekler", style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold)),
            SizedBox(height: 15),
            Expanded(
              child: ListView(
                scrollDirection: Axis.horizontal,
                children: [
                  _buildPlayerCard("Melih Yılmaz", "Forvet", "19", "assets/player1.jpg"),
                  _buildPlayerCard("Arda Güler", "Orta Saha", "18", "assets/player2.jpg"),
                ],
              ),
            ),
            SizedBox(height: 25),
            Text("Son Yüklenen Videolar", style: TextStyle(fontSize: 18, fontWeight: FontWeight.w500)),
            SizedBox(height: 10),
            _buildVideoListTile("Falso Şut Denemesi", "Caner Erkin Jr.", "2 dk önce"),
            _buildVideoListTile("Hızlı Dripling", "Rüzgarın Oğlu", "1 saat önce"),
          ],
        ),
      ),
      bottomNavigationBar: BottomNavigationBar(
        selectedItemColor: Color(0xFF1DB954),
        unselectedItemColor: Colors.grey,
        items: [
          BottomNavigationBarItem(icon: Icon(Icons.home), label: "Keşfet"),
          BottomNavigationBarItem(icon: Icon(Icons.video_library), label: "Videolar"),
          BottomNavigationBarItem(icon: Icon(Icons.person), label: "Profil"),
        ],
      ),
    );
  }

  Widget _buildPlayerCard(String name, String pos, String age, String img) {
    return Container(
      width: 250,
      margin: EdgeInsets.only(right: 15),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(20),
        gradient: LinearGradient(colors: [Colors.green.shade800, Colors.black], begin: Alignment.topLeft),
      ),
      child: Stack(
        children: [
          Center(child: Icon(Icons.person, size: 100, color: Colors.white24)), // Geçici Resim Yerine
          Positioned(
            bottom: 20,
            left: 20,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(name, style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold)),
                Text("$pos | $age Yaş", style: TextStyle(color: Colors.white70)),
              ],
            ),
          ),
          Positioned(
            top: 15,
            right: 15,
            child: Container(
              padding: EdgeInsets.all(6),
              decoration: BoxDecoration(color: Colors.amber, shape: BoxShape.circle),
              child: Icon(Icons.star, color: Colors.black, size: 20),
            ),
          )
        ],
      ),
    );
  }

  Widget _buildVideoListTile(String title, String user, String time) {
    return ListTile(
      leading: Container(width: 60, height: 40, color: Colors.white10, child: Icon(Icons.play_circle_fill)),
      title: Text(title),
      subtitle: Text("$user - $time"),
      trailing: Icon(Icons.more_vert),
    );
  }
}