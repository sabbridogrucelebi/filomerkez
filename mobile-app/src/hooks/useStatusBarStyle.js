import { useCallback } from 'react';
import { StatusBar } from 'react-native';
import { useFocusEffect } from '@react-navigation/native';

// Uygulama kökü 'light-content' kullanır (ekranların çoğu koyu zeminli).
// Açık zeminli ekranlar bunu çağırır: odaktayken koyu ikonlara geçer,
// odaktan çıkınca köke geri döner. Sekmeler mount kaldığı için sadece
// <StatusBar /> render etmek yetmez; odak takibi şart.
export default function useStatusBarStyle(style) {
    useFocusEffect(
        useCallback(() => {
            StatusBar.setBarStyle(style, true);
            return () => StatusBar.setBarStyle('light-content', true);
        }, [style])
    );
}
