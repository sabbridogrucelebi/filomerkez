import React, { useEffect, useRef } from 'react';
import { View, StyleSheet, Animated, Dimensions, Image } from 'react-native';

const { width, height } = Dimensions.get('window');

export default function GlobalSplashScreen({ onFinish }) {
    const bgOpacity = useRef(new Animated.Value(1)).current;

    useEffect(() => {
        // Video kaldırıldığı için 3 saniye sonra otomatik kapanacak şekilde ayarlıyoruz
        const timeout = setTimeout(() => {
            Animated.timing(bgOpacity, {
                toValue: 0,
                duration: 400,
                useNativeDriver: true,
            }).start(() => {
                if (onFinish) onFinish();
            });
        }, 1500);
        return () => clearTimeout(timeout);
    }, []);

    return (
        <Animated.View style={[styles.container, { opacity: bgOpacity }]}>
            <Image
                source={require('../../assets/splash-icon.png')}
                style={styles.image}
                resizeMode="contain"
            />
        </Animated.View>
    );
}

const styles = StyleSheet.create({
    container: {
        ...StyleSheet.absoluteFillObject,
        zIndex: 9999,
        backgroundColor: '#000000',
        justifyContent: 'center',
        alignItems: 'center'
    },
    image: {
        width: width * 0.8,
        height: height * 0.8,
    },
});
